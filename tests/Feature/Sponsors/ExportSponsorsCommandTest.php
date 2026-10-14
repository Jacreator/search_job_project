<?php

declare(strict_types=1);

use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\Team;
use App\Services\SponsorExport;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

const EXPORT_DATE = '2026-10-10';

function exportPath(Team $team): string
{
    return storage_path("app/exports/{$team->slug}_tech_sponsors_".EXPORT_DATE.'.csv');
}

/**
 * A finished sponsor with a website, which the export includes by default.
 *
 * @param  array<string, mixed>  $attributes
 */
function exportableSponsor(Team $team, array $attributes = []): Sponsor
{
    return Sponsor::factory()->for($team)->create([
        'status' => SponsorStatus::Done,
        'website' => 'https://example.test',
        'confidence' => 80,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $options
 */
function exportSponsors(Team $team, array $options = []): PendingCommand
{
    return test()->artisan('sponsors:export', ['--team' => $team->slug, ...$options]);
}

/**
 * The rows of the team's export file, header first, without the BOM.
 *
 * @return list<list<string|null>>
 */
function exportedRows(Team $team): array
{
    $content = (string) file_get_contents(exportPath($team));
    $lines = explode("\n", trim(substr($content, 3)));

    return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), $lines);
}

/**
 * The names in the team's export file, in file order.
 *
 * @return list<string|null>
 */
function exportedNames(Team $team): array
{
    return array_column(array_slice(exportedRows($team), 1), 0);
}

beforeEach(function () {
    $this->travelTo(EXPORT_DATE.' 09:00:00');

    // Use an empty storage folder, so the tests never write into the real exports folder.
    $this->storage = sys_get_temp_dir().'/sponsor-finder-'.uniqid();
    File::ensureDirectoryExists($this->storage.'/app');
    $this->app->useStoragePath($this->storage);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

it('writes the headers and one row per finished sponsor with a website', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, [
        'name' => 'Acme Software Ltd',
        'town' => 'Sheffield',
        'region' => 'Yorkshire',
        'company_number' => '01234567',
        'sic_codes' => ['62012', '62020'],
        'website' => 'https://acme.test',
        'confidence' => 85,
        'confirmed' => true,
    ]);

    exportSponsors($team)
        ->expectsOutput('Exported 1 sponsor to '.exportPath($team))
        ->assertSuccessful();

    expect(exportedRows($team))->toBe([
        SponsorExport::HEADERS,
        ['Acme Software Ltd', 'Sheffield', 'Yorkshire', '01234567', '62012, 62020', 'https://acme.test', '85', 'Yes', 'No', 'No'],
    ]);
});

it('uses the spec column names', function () {
    expect(SponsorExport::HEADERS)->toBe([
        'Name', 'Town', 'Region', 'Company number', 'SIC codes', 'Website', 'Confidence', 'Confirmed', 'Scale-up', 'Needs second check',
    ]);
});

it('starts the file with a UTF-8 BOM', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Café Señor Ltd']);

    exportSponsors($team)->assertSuccessful();

    expect(file_get_contents(exportPath($team)))->toStartWith("\xEF\xBB\xBFName,Town,");
    expect(exportedNames($team))->toBe(['Café Señor Ltd']);
});

it('leaves empty cells for missing values', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Bare Ltd', 'town' => '', 'region' => null, 'company_number' => null, 'sic_codes' => null]);

    exportSponsors($team)->assertSuccessful();

    expect(exportedRows($team)[1])->toBe(['Bare Ltd', '', '', '', '', 'https://example.test', '80', 'No', 'No', 'No']);
});

it('exports only done rows with a website', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Done With Site']);
    exportableSponsor($team, ['name' => 'Done No Site', 'website' => null, 'confidence' => null]);
    exportableSponsor($team, ['name' => 'Done Empty Site', 'website' => '']);

    foreach ([SponsorStatus::Pending, SponsorStatus::ChDone, SponsorStatus::Skipped, SponsorStatus::Failed] as $status) {
        exportableSponsor($team, ['status' => $status]);
    }

    exportSponsors($team)->assertSuccessful();

    expect(exportedNames($team))->toBe(['Done With Site']);
});

it('never exports rows from other teams', function () {
    $team = Team::factory()->create();
    $other = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Ours']);
    exportableSponsor($other, ['name' => 'Theirs']);

    exportSponsors($team)->assertSuccessful();

    expect(exportedNames($team))->toBe(['Ours'])
        ->and(file_exists(exportPath($other)))->toBeFalse();
});

it('orders rows by confidence, highest first, then by name', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Middle B', 'confidence' => 75]);
    exportableSponsor($team, ['name' => 'Low', 'confidence' => 55]);
    exportableSponsor($team, ['name' => 'High', 'confidence' => 100]);
    exportableSponsor($team, ['name' => 'Middle A', 'confidence' => 75]);

    exportSponsors($team)->assertSuccessful();

    expect(exportedNames($team))->toBe(['High', 'Middle A', 'Middle B', 'Low']);
});

it('leaves out rows under a confidence of 50 by default', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Fifty', 'confidence' => 50]);
    exportableSponsor($team, ['name' => 'Forty Nine', 'confidence' => 49]);

    exportSponsors($team)->assertSuccessful();

    expect(exportedNames($team))->toBe(['Fifty']);
});

it('filters by minimum confidence', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Eighty', 'confidence' => 80]);
    exportableSponsor($team, ['name' => 'Seventy', 'confidence' => 70]);
    exportableSponsor($team, ['name' => 'Twenty', 'confidence' => 20]);

    exportSponsors($team, ['--min-confidence' => '75'])->assertSuccessful();
    expect(exportedNames($team))->toBe(['Eighty']);

    exportSponsors($team, ['--min-confidence' => '0'])->assertSuccessful();
    expect(exportedNames($team))->toBe(['Eighty', 'Seventy', 'Twenty']);
});

it('filters by town, ignoring case', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'In Sheffield', 'town' => 'Sheffield']);
    exportableSponsor($team, ['name' => 'In Leeds', 'town' => 'Leeds']);

    exportSponsors($team, ['--town' => 'sheffield'])->assertSuccessful();

    expect(exportedNames($team))->toBe(['In Sheffield']);
});

it('filters by region, ignoring case', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'In Yorkshire', 'region' => 'Yorkshire']);
    exportableSponsor($team, ['name' => 'In London', 'region' => 'London']);
    exportableSponsor($team, ['name' => 'No Region', 'region' => null]);

    exportSponsors($team, ['--region' => 'YORKSHIRE'])->assertSuccessful();

    expect(exportedNames($team))->toBe(['In Yorkshire']);
});

it('filters by confirmed', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Checked', 'confirmed' => true]);
    exportableSponsor($team, ['name' => 'Unchecked', 'confirmed' => false]);

    exportSponsors($team, ['--confirmed' => true])->assertSuccessful();

    expect(exportedNames($team))->toBe(['Checked']);
});

it('filters by Scale-up', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Scale Alone', 'route' => 'Scale-up']);
    exportableSponsor($team, ['name' => 'Scale Joined', 'route' => 'Skilled Worker, Scale-up']);
    exportableSponsor($team, ['name' => 'Skilled Only', 'route' => 'Skilled Worker']);

    exportSponsors($team, ['--scale-up' => true])->assertSuccessful();

    expect(exportedNames($team))->toBe(['Scale Alone', 'Scale Joined']);
});

it('combines filters', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'Match', 'town' => 'Sheffield', 'region' => 'Yorkshire', 'confirmed' => true, 'confidence' => 90]);
    exportableSponsor($team, ['name' => 'Wrong Town', 'town' => 'Leeds', 'region' => 'Yorkshire', 'confirmed' => true, 'confidence' => 90]);
    exportableSponsor($team, ['name' => 'Not Confirmed', 'town' => 'Sheffield', 'region' => 'Yorkshire', 'confidence' => 90]);
    exportableSponsor($team, ['name' => 'Too Low', 'town' => 'Sheffield', 'region' => 'Yorkshire', 'confirmed' => true, 'confidence' => 60]);

    exportSponsors($team, ['--town' => 'Sheffield', '--region' => 'Yorkshire', '--confirmed' => true, '--min-confidence' => '75'])
        ->assertSuccessful();

    expect(exportedNames($team))->toBe(['Match']);
});

it('marks the Scale-up column from the route, alone or with other routes', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'A Alone', 'route' => 'Scale-up']);
    exportableSponsor($team, ['name' => 'B Joined', 'route' => 'Skilled Worker, Scale-up']);
    exportableSponsor($team, ['name' => 'C Skilled', 'route' => 'Skilled Worker']);

    exportSponsors($team)->assertSuccessful();

    expect(array_column(array_slice(exportedRows($team), 1), 8, 0))->toBe([
        'A Alone' => 'Yes',
        'B Joined' => 'Yes',
        'C Skilled' => 'No',
    ]);
});

it('escapes cells a spreadsheet would run as a formula', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, [
        'name' => '=HYPERLINK("http://evil.test","Acme")',
        'town' => '+Sheffield',
        'region' => '-Yorkshire',
        'company_number' => '@123',
        'website' => '=cmd',
    ]);

    exportSponsors($team)->assertSuccessful();

    expect(array_slice(exportedRows($team)[1], 0, 6))->toBe([
        '\'=HYPERLINK("http://evil.test","Acme")',
        "'+Sheffield",
        "'-Yorkshire",
        "'@123",
        '',
        "'=cmd",
    ]);
});

it('writes only the headers when nothing matches', function () {
    $team = Team::factory()->create();

    exportSponsors($team)
        ->expectsOutput('Exported 0 sponsors to '.exportPath($team))
        ->assertSuccessful();

    expect(exportedRows($team))->toBe([SponsorExport::HEADERS]);
});

it('replaces an export made earlier the same day and leaves no partial file', function () {
    $team = Team::factory()->create();
    exportableSponsor($team, ['name' => 'First']);
    exportSponsors($team)->assertSuccessful();

    exportableSponsor($team, ['name' => 'Second']);
    exportSponsors($team)->assertSuccessful();

    expect(exportedNames($team))->toBe(['First', 'Second'])
        ->and(File::files(storage_path('app/exports')))->toHaveCount(1);
});

it('fails without a team', function () {
    $this->artisan('sponsors:export')
        ->expectsOutput('The --team option is required.')
        ->assertFailed();
});

it('fails for an unknown team', function () {
    $this->artisan('sponsors:export', ['--team' => 'nope'])
        ->expectsOutput('Team [nope] does not exist.')
        ->assertFailed();
});

it('fails for a minimum confidence that is not a whole number from 0 to 100', function (string $value) {
    $team = Team::factory()->create();

    exportSponsors($team, ['--min-confidence' => $value])
        ->expectsOutput('The --min-confidence option must be a whole number from 0 to 100.')
        ->assertFailed();

    expect(file_exists(exportPath($team)))->toBeFalse();
})->with(['abc', '-1', '101', '7.5', '']);
