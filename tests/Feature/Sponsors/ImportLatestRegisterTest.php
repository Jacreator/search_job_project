<?php

declare(strict_types=1);

use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const CONTENT_URL = 'https://www.gov.uk/api/content/government/publications/register-of-licensed-sponsors-workers';
const CSV_URL = 'https://assets.publishing.service.gov.uk/media/bbbb/SP_-_Worker_and_Temporary_Worker_Web_Register_-_1999-01-01.csv';

function downloadedRegister(): string
{
    return storage_path('app/imports/register-1999-01-01.csv');
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

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    @unlink(downloadedRegister());
});

afterEach(function () {
    @unlink(downloadedRegister());
    @unlink(downloadedRegister().'.part');
});

it('downloads the latest register and imports it when no file is given', function () {
    Http::fake([
        CONTENT_URL => Http::response(registerContent()),
        CSV_URL => Http::response(registerBody()),
    ]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('Downloaded register-1999-01-01.csv')
        ->assertSuccessful();

    expect(downloadedRegister())->toBeFile()
        ->and(file_get_contents(downloadedRegister()))->toBe(registerBody())
        ->and($team->sponsors()->count())->toBe(6);
    Http::assertSentCount(2);
});

it('reuses a register that was already downloaded', function () {
    copy(base_path('tests/Fixtures/register/workers.csv'), downloadedRegister());
    Http::fake([CONTENT_URL => Http::response(registerContent())]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('Already downloaded, using register-1999-01-01.csv')
        ->assertSuccessful();

    expect($team->sponsors()->count())->toBe(6);
    Http::assertNotSent(fn ($request) => $request->url() === CSV_URL);
});

it('fails when the publication has no register CSV', function () {
    $content = registerContent();
    $content['details']['attachments'] = [$content['details']['attachments'][0]];
    Http::fake([CONTENT_URL => Http::response($content)]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('no register CSV was found')
        ->assertFailed();

    expect(Sponsor::query()->count())->toBe(0);
});

it('ignores a CSV link that is not on the GOV.UK assets host', function () {
    $content = registerContent();
    $content['details']['attachments'][1]['url'] = 'https://example.com/register-1999-01-01.csv';
    Http::fake([CONTENT_URL => Http::response($content)]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('no register CSV was found')
        ->assertFailed();

    Http::assertSentCount(1);
});

it('fails when the publication page cannot be loaded', function () {
    Http::fake([CONTENT_URL => Http::response('Server error', 500)]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('the publication page could not be loaded')
        ->assertFailed();

    expect(Sponsor::query()->count())->toBe(0);
});

it('fails and leaves no file behind when the CSV download fails', function () {
    Http::fake([
        CONTENT_URL => Http::response(registerContent()),
        CSV_URL => Http::response('Server error', 500),
    ]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('the register CSV could not be downloaded')
        ->assertFailed();

    expect(downloadedRegister())->not->toBeFile()
        ->and(downloadedRegister().'.part')->not->toBeFile()
        ->and(Sponsor::query()->count())->toBe(0);
});

it('fails and leaves no file behind when the CSV is empty', function () {
    Http::fake([
        CONTENT_URL => Http::response(registerContent()),
        CSV_URL => Http::response(''),
    ]);
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['--team' => $team->slug])
        ->expectsOutputToContain('the register CSV was empty or too large')
        ->assertFailed();

    expect(downloadedRegister())->not->toBeFile()
        ->and(downloadedRegister().'.part')->not->toBeFile();
});

it('does not download when a named file is missing', function () {
    Http::fake();
    $team = Team::factory()->create();

    $this->artisan('sponsors:import', ['file' => 'missing-register.csv', '--team' => $team->slug])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();

    Http::assertNothingSent();
});

it('checks the team before downloading', function () {
    Http::fake();

    $this->artisan('sponsors:import', ['--team' => 'no-such-team'])
        ->expectsOutputToContain('Team [no-such-team] does not exist.')
        ->assertFailed();

    Http::assertNothingSent();
});
