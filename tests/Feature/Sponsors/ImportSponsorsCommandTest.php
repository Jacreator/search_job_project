<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Testing\PendingCommand;

const REGISTER_HEADER = 'Organisation Name,Town/City,County,Type & Rating,Route';

function fixtureRegister(): string
{
    return base_path('tests/Fixtures/register/workers.csv');
}

/**
 * Write a register CSV with the given data lines and return its path.
 *
 * @param  list<string>  $lines
 */
function registerCsv(array $lines, string $header = REGISTER_HEADER): string
{
    $path = tempnam(sys_get_temp_dir(), 'register');
    file_put_contents($path, implode("\n", [$header, ...$lines])."\n");

    return $path;
}

function importInto(Team $team, string $path): PendingCommand
{
    return test()->artisan('sponsors:import', ['file' => $path, '--team' => $team->slug]);
}

function summary(int $read, int $ignored, int $new, int $existing, int $movedToSkipped = 0, int $movedToPending = 0): array
{
    return [
        ['Register rows read', $read],
        ['Ignored (other routes)', $ignored],
        ['New sponsors', $new],
        ['Existing sponsors', $existing],
        ['Moved to skipped (B rating)', $movedToSkipped],
        ['Moved back to pending (A rating)', $movedToPending],
    ];
}

function sponsorNamed(Team $team, string $name): Sponsor
{
    return $team->sponsors()->where('name', $name)->sole();
}

it('imports a register file into a team', function () {
    $team = Team::factory()->create();

    importInto($team, fixtureRegister())
        ->expectsTable(['Result', 'Count'], summary(read: 11, ignored: 2, new: 6, existing: 0))
        ->assertSuccessful();

    expect($team->sponsors()->count())->toBe(6);

    $acme = sponsorNamed($team, 'Acme Software Ltd');
    expect($acme->town)->toBe('Sheffield')
        ->and($acme->county)->toBe('South Yorkshire')
        ->and($acme->route)->toBe('Skilled Worker, Global Business Mobility: Senior or Specialist Worker')
        ->and($acme->rating)->toBe('Worker (A rating)')
        ->and($acme->rating_grade)->toBe('A')
        ->and($acme->rating_grade_manual)->toBeFalse()
        ->and($acme->region)->toBe('Yorkshire')
        ->and($acme->priority)->toBe(100)
        ->and($acme->status)->toBe(SponsorStatus::Pending)
        ->and($acme->skip_reason)->toBeNull();

    expect(sponsorNamed($team, 'Gamma Cloud Ltd')->rating_grade)->toBe('A')
        ->and(sponsorNamed($team, 'Gamma Cloud Ltd')->region)->toBeNull()
        ->and(sponsorNamed($team, 'Gamma Cloud Ltd')->priority)->toBe(0)
        ->and(sponsorNamed($team, 'Theta Tech Ltd')->rating_grade)->toBeNull()
        ->and(sponsorNamed($team, 'Theta Tech Ltd')->status)->toBe(SponsorStatus::Pending);
});

it('ignores rows with other routes', function () {
    $team = Team::factory()->create();

    importInto($team, fixtureRegister())->assertSuccessful();

    expect($team->sponsors()->whereIn('name', ['Delta Church', 'Epsilon Arts Ltd'])->exists())->toBeFalse();
});

it('merges register rows for the same name and town into one sponsor', function () {
    $team = Team::factory()->create();

    importInto($team, fixtureRegister())->assertSuccessful();

    $iota = sponsorNamed($team, 'Iota Graduates Ltd');
    expect($team->sponsors()->where('town', 'York')->count())->toBe(1)
        ->and($iota->route)->toBe('Global Business Mobility: Graduate Trainee, Skilled Worker, Scale-up')
        ->and($iota->county)->toBe('North Yorkshire')
        ->and($iota->rating_grade)->toBe('A')
        ->and($iota->region)->toBe('Yorkshire')
        ->and($iota->priority)->toBe(70);
});

it('lists a repeated route once', function () {
    $team = Team::factory()->create();

    importInto($team, registerCsv([
        'Acme Ltd,Leeds,,Worker (A rating),Skilled Worker',
        'Acme Ltd,Leeds,,Worker (A rating),Skilled Worker',
    ]))->assertSuccessful();

    expect(sponsorNamed($team, 'Acme Ltd')->route)->toBe('Skilled Worker');
});

it('merges rows for the same sponsor that fall in different chunks', function () {
    $team = Team::factory()->create();
    $lines = array_map(fn (int $i) => "Company {$i} Ltd,Leeds,,Worker (A rating),Skilled Worker", range(1, 500));
    $lines[] = 'Company 1 Ltd,Leeds,,Worker (B rating),Scale-up';

    importInto($team, registerCsv($lines))->assertSuccessful();

    $first = sponsorNamed($team, 'Company 1 Ltd');
    expect($team->sponsors()->count())->toBe(500)
        ->and($first->route)->toBe('Skilled Worker, Scale-up')
        ->and($first->rating_grade)->toBe('B')
        ->and($first->status)->toBe(SponsorStatus::Skipped)
        ->and($first->skip_reason)->toBe(SkipReason::BRating);
});

it('gives a B grade priority over an A grade for the same sponsor', function () {
    $team = Team::factory()->create();

    importInto($team, registerCsv([
        'Acme Ltd,Leeds,,Worker (A rating),Skilled Worker',
        'Acme Ltd,Leeds,,Worker (B rating),Scale-up',
    ]))->assertSuccessful();

    $acme = sponsorNamed($team, 'Acme Ltd');
    expect($acme->rating_grade)->toBe('B')
        ->and($acme->rating)->toBe('Worker (B rating)')
        ->and($acme->status)->toBe(SponsorStatus::Skipped);
});

it('re-imports the same file without duplicates and keeps existing websites', function () {
    $team = Team::factory()->create();
    importInto($team, fixtureRegister())->assertSuccessful();

    sponsorNamed($team, 'Acme Software Ltd')->update([
        'status' => SponsorStatus::Done,
        'company_number' => '01234567',
        'sic_codes' => ['62012'],
        'is_tech' => true,
        'website' => 'https://acme.example',
        'confidence' => 90,
        'confirmed' => true,
    ]);

    importInto($team, fixtureRegister())
        ->expectsTable(['Result', 'Count'], summary(read: 11, ignored: 2, new: 0, existing: 6))
        ->assertSuccessful();

    $acme = sponsorNamed($team, 'Acme Software Ltd');
    expect($team->sponsors()->count())->toBe(6)
        ->and($acme->status)->toBe(SponsorStatus::Done)
        ->and($acme->company_number)->toBe('01234567')
        ->and($acme->sic_codes)->toBe(['62012'])
        ->and($acme->website)->toBe('https://acme.example')
        ->and($acme->confidence)->toBe(90)
        ->and($acme->confirmed)->toBeTrue();
});

it('stores a missing town as an empty string and does not duplicate it on re-import', function () {
    $team = Team::factory()->create();

    importInto($team, fixtureRegister())->assertSuccessful();
    importInto($team, fixtureRegister())->assertSuccessful();

    expect($team->sponsors()->where('name', 'Zeta Apps Ltd')->count())->toBe(1)
        ->and(sponsorNamed($team, 'Zeta Apps Ltd')->town)->toBe('');
});

it('imports the same file into a second team as separate rows', function () {
    $first = Team::factory()->create();
    $second = Team::factory()->create();

    importInto($first, fixtureRegister())->assertSuccessful();
    importInto($second, fixtureRegister())
        ->expectsTable(['Result', 'Count'], summary(read: 11, ignored: 2, new: 6, existing: 0))
        ->assertSuccessful();

    expect($first->sponsors()->count())->toBe(6)
        ->and($second->sponsors()->count())->toBe(6);
});

it('skips new B-rated rows when skip_b_rated is on', function () {
    $team = Team::factory()->create();

    importInto($team, fixtureRegister())->assertSuccessful();

    $beta = sponsorNamed($team, 'Beta Data Ltd');
    expect($beta->rating_grade)->toBe('B')
        ->and($beta->status)->toBe(SponsorStatus::Skipped)
        ->and($beta->skip_reason)->toBe(SkipReason::BRating);
});

it('imports new B-rated rows as pending when skip_b_rated is off', function () {
    config(['sponsor-finder.skip_b_rated' => false]);
    $team = Team::factory()->create();

    importInto($team, fixtureRegister())->assertSuccessful();

    $beta = sponsorNamed($team, 'Beta Data Ltd');
    expect($beta->rating_grade)->toBe('B')
        ->and($beta->status)->toBe(SponsorStatus::Pending)
        ->and($beta->skip_reason)->toBeNull();
});

it('skips a done row that moves from A to B and keeps its results', function () {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => 'A',
        'status' => SponsorStatus::Done,
        'company_number' => '01234567',
        'website' => 'https://acme.example',
        'confidence' => 80,
        'confirmed' => true,
    ]);

    importInto($sponsor->team, registerCsv(['Acme Ltd,Leeds,,Worker (B rating),Skilled Worker']))
        ->expectsTable(['Result', 'Count'], summary(read: 1, ignored: 0, new: 0, existing: 1, movedToSkipped: 1))
        ->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->rating_grade)->toBe('B')
        ->and($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::BRating)
        ->and($sponsor->company_number)->toBe('01234567')
        ->and($sponsor->website)->toBe('https://acme.example')
        ->and($sponsor->confidence)->toBe(80)
        ->and($sponsor->confirmed)->toBeTrue();
});

it('returns a B-rating skip to pending when it moves from B to A and keeps its results', function () {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => 'B',
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::BRating,
        'company_number' => '01234567',
        'sic_codes' => ['62012'],
    ]);

    importInto($sponsor->team, registerCsv(['Acme Ltd,Leeds,,Worker (A rating),Skilled Worker']))
        ->expectsTable(['Result', 'Count'], summary(read: 1, ignored: 0, new: 0, existing: 1, movedToPending: 1))
        ->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->rating_grade)->toBe('A')
        ->and($sponsor->status)->toBe(SponsorStatus::Pending)
        ->and($sponsor->skip_reason)->toBeNull()
        ->and($sponsor->company_number)->toBe('01234567')
        ->and($sponsor->sic_codes)->toBe(['62012']);
});

it('does not change the status of a row skipped for another reason', function (string $from, string $rating) {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => $from,
        'status' => SponsorStatus::Skipped,
        'skip_reason' => SkipReason::NotTech,
    ]);

    importInto($sponsor->team, registerCsv(["Acme Ltd,Leeds,,{$rating},Skilled Worker"]))
        ->expectsTable(['Result', 'Count'], summary(read: 1, ignored: 0, new: 0, existing: 1))
        ->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::NotTech);
})->with([
    'B to A' => ['B', 'Worker (A rating)'],
    'A to B' => ['A', 'Worker (B rating)'],
]);

it('leaves a row unchanged when nothing in the register changed', function () {
    $team = Team::factory()->create();
    importInto($team, fixtureRegister())->assertSuccessful();
    $before = sponsorNamed($team, 'Acme Software Ltd');

    $this->travel(1)->hours();
    importInto($team, fixtureRegister())->assertSuccessful();

    $after = sponsorNamed($team, 'Acme Software Ltd');
    expect($after->getAttributes())->toBe($before->getAttributes());
});

it('keeps the stored grade when the register value cannot be parsed', function () {
    $sponsor = Sponsor::factory()->create(['name' => 'Acme Ltd', 'town' => 'Leeds', 'rating_grade' => 'A']);

    importInto($sponsor->team, registerCsv(['Acme Ltd,Leeds,,Worker (UK Expansion Worker: Provisional ),Skilled Worker']))
        ->assertSuccessful();

    expect($sponsor->refresh()->rating_grade)->toBe('A')
        ->and($sponsor->status)->toBe(SponsorStatus::Pending);
});

it('keeps a hand-set grade when the register value cannot be parsed', function () {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => 'A',
        'rating_grade_manual' => true,
    ]);

    importInto($sponsor->team, registerCsv(['Acme Ltd,Leeds,,Worker (UK Expansion Worker: Provisional ),Skilled Worker']))
        ->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->rating_grade)->toBe('A')
        ->and($sponsor->rating_grade_manual)->toBeTrue();
});

it('skips a row whose missing grade becomes B', function () {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => null,
        'status' => SponsorStatus::Done,
    ]);

    importInto($sponsor->team, registerCsv(['Acme Ltd,Leeds,,Worker (B rating),Skilled Worker']))
        ->expectsTable(['Result', 'Count'], summary(read: 1, ignored: 0, new: 0, existing: 1, movedToSkipped: 1))
        ->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->rating_grade)->toBe('B')
        ->and($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::BRating);
});

it('changes only the grade when a missing grade becomes A', function () {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => null,
        'status' => SponsorStatus::Done,
    ]);

    importInto($sponsor->team, registerCsv(['Acme Ltd,Leeds,,Worker (A rating),Skilled Worker']))->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->rating_grade)->toBe('A')
        ->and($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->skip_reason)->toBeNull();
});

it('replaces a hand-set grade with the register grade', function (string $rating, string $grade) {
    $sponsor = Sponsor::factory()->create([
        'name' => 'Acme Ltd',
        'town' => 'Leeds',
        'rating_grade' => 'A',
        'rating_grade_manual' => true,
    ]);

    importInto($sponsor->team, registerCsv(["Acme Ltd,Leeds,,{$rating},Skilled Worker"]))->assertSuccessful();

    $sponsor->refresh();
    expect($sponsor->rating_grade)->toBe($grade)
        ->and($sponsor->rating_grade_manual)->toBeFalse();
})->with([
    'same grade' => ['Worker (A rating)', 'A'],
    'different grade' => ['Worker (B rating)', 'B'],
]);

it('sets region and priority from the town, ignoring case', function () {
    $team = Team::factory()->create();

    importInto($team, registerCsv([
        'One Ltd,sheffield,,Worker (A rating),Skilled Worker',
        'Two Ltd,SHEFFIELD,,Worker (A rating),Skilled Worker',
        'Three Ltd,Sheffield,,Worker (A rating),Skilled Worker',
        'Four Ltd,Bristol,,Worker (A rating),Skilled Worker',
    ]))->assertSuccessful();

    foreach (['One Ltd', 'Two Ltd', 'Three Ltd'] as $name) {
        expect(sponsorNamed($team, $name))
            ->region->toBe('Yorkshire')
            ->priority->toBe(100);
    }

    expect(sponsorNamed($team, 'Four Ltd'))
        ->region->toBeNull()
        ->priority->toBe(0);
});

it('reads a file name from storage/app/imports', function () {
    $team = Team::factory()->create();
    $name = 'test-register-'.uniqid().'.csv';
    copy(fixtureRegister(), storage_path('app/imports/'.$name));

    try {
        importInto($team, $name)->assertSuccessful();
    } finally {
        unlink(storage_path('app/imports/'.$name));
    }

    expect($team->sponsors()->count())->toBe(6);
});

it('fails when the file does not exist', function () {
    $team = Team::factory()->create();

    importInto($team, 'missing-register.csv')
        ->expectsOutputToContain('does not exist')
        ->assertFailed();

    expect(Sponsor::query()->count())->toBe(0);
});

it('fails when a required header is missing', function () {
    $team = Team::factory()->create();

    importInto($team, registerCsv(
        ['Acme Ltd,Leeds,Worker (A rating),Skilled Worker'],
        header: 'Organisation Name,Town/City,Type & Rating,Route',
    ))
        ->expectsOutputToContain('missing required headers: County')
        ->assertFailed();

    expect(Sponsor::query()->count())->toBe(0);
});

it('fails when the file is empty', function () {
    $team = Team::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'register');

    importInto($team, $path)
        ->expectsOutputToContain('missing required headers')
        ->assertFailed();
});

it('fails when the team does not exist', function () {
    $this->artisan('sponsors:import', ['file' => fixtureRegister(), '--team' => 'no-such-team'])
        ->expectsOutputToContain('Team [no-such-team] does not exist.')
        ->assertFailed();

    expect(Sponsor::query()->count())->toBe(0);
});

it('fails when no team is given', function () {
    $this->artisan('sponsors:import', ['file' => fixtureRegister()])
        ->expectsOutputToContain('The --team option is required.')
        ->assertFailed();
});
