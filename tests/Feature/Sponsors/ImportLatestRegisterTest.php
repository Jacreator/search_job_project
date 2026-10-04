<?php

declare(strict_types=1);

use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Testing\PendingCommand;

const CONTENT_URL = 'https://www.gov.uk/api/content/government/publications/register-of-licensed-sponsors-workers';
const CSV_URL = 'https://assets.publishing.service.gov.uk/media/bbbb/SP_-_Worker_and_Temporary_Worker_Web_Register_-_1999-01-01.csv';

function imports(string $file = ''): string
{
    return storage_path('app/imports'.($file === '' ? '' : '/'.$file));
}

/**
 * @return array<string, mixed>
 */
function registerContent(): array
{
    return json_decode((string) file_get_contents(base_path('tests/Fixtures/gov-uk/register-content.json')), true);
}

function registerBody(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/register/workers.csv'));
}

/**
 * Save a register in the imports folder, as if downloaded earlier.
 */
function saveRegister(string $file, ?string $body = null): void
{
    file_put_contents(imports($file), $body ?? registerBody());
}

function importLatest(Team $team): PendingCommand
{
    return test()->artisan('sponsors:import', ['--team' => $team->slug]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();

    // Use an empty storage folder, so registers saved locally never affect these tests.
    $this->storage = sys_get_temp_dir().'/sponsor-finder-'.uniqid();
    File::ensureDirectoryExists($this->storage.'/app/imports');
    $this->app->useStoragePath($this->storage);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

it('downloads the latest register and imports it when no file is given', function () {
    Http::fake([
        CONTENT_URL => Http::response(registerContent()),
        CSV_URL => Http::response(registerBody()),
    ]);
    $team = Team::factory()->create();

    importLatest($team)
        ->expectsOutputToContain('Downloaded register-1999-01-01.csv')
        ->assertSuccessful();

    expect(imports('register-1999-01-01.csv'))->toBeFile()
        ->and(file_get_contents(imports('register-1999-01-01.csv')))->toBe(registerBody())
        ->and($team->sponsors()->count())->toBe(6);
    Http::assertSentCount(2);
});

it('reuses a register that was already downloaded', function () {
    saveRegister('register-1999-01-01.csv');
    Http::fake([CONTENT_URL => Http::response(registerContent())]);
    $team = Team::factory()->create();

    importLatest($team)
        ->expectsOutputToContain('Already downloaded, using register-1999-01-01.csv')
        ->assertSuccessful();

    expect($team->sponsors()->count())->toBe(6);
    Http::assertNotSent(fn ($request) => $request->url() === CSV_URL);
});

it('falls back to the newest saved register when the download fails', function (Closure $fake, string $reason) {
    $fake();
    Log::spy();
    saveRegister('register-1998-06-01.csv', "Organisation Name,Town/City,County,Type & Rating,Route\nOld Ltd,Leeds,,Worker (A rating),Skilled Worker\n");
    saveRegister('register-1998-12-01.csv');
    $team = Team::factory()->create();

    importLatest($team)
        ->expectsOutputToContain($reason)
        ->expectsOutputToContain('Importing the newest saved register instead: register-1998-12-01.csv')
        ->assertSuccessful();

    expect($team->sponsors()->count())->toBe(6)
        ->and($team->sponsors()->where('name', 'Old Ltd')->exists())->toBeFalse()
        ->and(imports('register-1999-01-01.csv'))->not->toBeFile()
        ->and(imports('register-1999-01-01.csv.part'))->not->toBeFile();

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => str_contains($message, 'imported a saved register instead')
            && $context['file'] === 'register-1998-12-01.csv'
            && str_contains($context['reason'], $reason),
    );
})->with([
    'publication page cannot be loaded' => [
        fn () => Http::fake([CONTENT_URL => Http::response('Server error', 500)]),
        'the publication page could not be loaded',
    ],
    'publication has no register CSV' => [
        function () {
            $content = registerContent();
            $content['details']['attachments'] = [$content['details']['attachments'][0]];
            Http::fake([CONTENT_URL => Http::response($content)]);
        },
        'no register CSV was found',
    ],
    'CSV link is not on the GOV.UK assets host' => [
        function () {
            $content = registerContent();
            $content['details']['attachments'][1]['url'] = 'https://example.com/register-1999-01-01.csv';
            Http::fake([CONTENT_URL => Http::response($content)]);
        },
        'no register CSV was found',
    ],
    'CSV download fails' => [
        fn () => Http::fake([
            CONTENT_URL => Http::response(registerContent()),
            CSV_URL => Http::response('Server error', 500),
        ]),
        'the register CSV could not be downloaded',
    ],
    'CSV is empty' => [
        fn () => Http::fake([
            CONTENT_URL => Http::response(registerContent()),
            CSV_URL => Http::response(''),
        ]),
        'the register CSV was empty or too large',
    ],
]);

it('never downloads from a host other than GOV.UK assets', function () {
    $content = registerContent();
    $content['details']['attachments'][1]['url'] = 'https://example.com/register-1999-01-01.csv';
    Http::fake([CONTENT_URL => Http::response($content)]);
    saveRegister('register-1998-12-01.csv');

    importLatest(Team::factory()->create())->assertSuccessful();

    Http::assertSentCount(1);
});

it('ignores saved files whose name has no register date', function () {
    Http::fake([CONTENT_URL => Http::response('Server error', 500)]);
    saveRegister('register-1998-12-01.csv');
    saveRegister('register-latest.csv', "Organisation Name,Town/City,County,Type & Rating,Route\nOther Ltd,Leeds,,Worker (A rating),Skilled Worker\n");
    saveRegister('register-1999-01-01.csv.part', 'partial');
    $team = Team::factory()->create();

    importLatest($team)
        ->expectsOutputToContain('Importing the newest saved register instead: register-1998-12-01.csv')
        ->assertSuccessful();

    expect($team->sponsors()->count())->toBe(6);
});

it('fails when the download fails and no register is saved', function () {
    Http::fake([CONTENT_URL => Http::response('Server error', 500)]);
    Log::spy();
    $team = Team::factory()->create();

    importLatest($team)
        ->expectsOutputToContain('the publication page could not be loaded')
        ->expectsOutputToContain('No saved register-*.csv was found in storage/app/imports either.')
        ->assertFailed();

    expect(Sponsor::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('warning');
});

it('does not download when a named file is missing', function () {
    Http::fake();
    saveRegister('register-1998-12-01.csv');
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['file' => 'missing-register.csv', '--team' => $team->slug])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();

    Http::assertNothingSent();
    expect(Sponsor::query()->count())->toBe(0);
});

it('checks the team before downloading', function () {
    Http::fake();

    $this->artisan('sponsors:import', ['--team' => 'no-such-team'])
        ->expectsOutputToContain('Team [no-such-team] does not exist.')
        ->assertFailed();

    Http::assertNothingSent();
});
