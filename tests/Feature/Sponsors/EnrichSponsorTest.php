<?php

declare(strict_types=1);

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Enums\TechReason;
use App\Exceptions\CompaniesHouseRequestFailed;
use App\Exceptions\WebSearchRequestFailed;
use App\Jobs\EnrichSponsor;
use App\Models\Sponsor;
use App\Services\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

const ENRICH_CH_SEARCH = 'https://api.company-information.service.gov.uk/search/companies*';
const ENRICH_CH_PROFILE = 'https://api.company-information.service.gov.uk/company/*';
const ENRICH_BRAVE = 'https://api.search.brave.com/*';

/**
 * @return array<string, mixed>
 */
function enrichFixture(string $path): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/{$path}.json")), true);
}

/**
 * @param  array<string, mixed>  $profile
 * @param  list<array{url: string, title: string}>  $results
 */
function fakeApis(array $profile = [], array $results = [], string $homepage = '<p>Acme Software builds apps.</p>'): void
{
    Http::fake([
        ENRICH_CH_SEARCH => Http::response(enrichFixture('companies-house/search-match')),
        ENRICH_CH_PROFILE => Http::response([...enrichFixture('companies-house/profile'), ...$profile]),
        ENRICH_BRAVE => Http::response(['web' => ['results' => $results]]),
        'https://www.acmesoftware.co.uk' => Http::response($homepage),
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function acme(array $attributes = []): Sponsor
{
    return Sponsor::factory()->create(['name' => 'Acme Software Ltd', 'town' => 'Sheffield', ...$attributes]);
}

function enrich(Sponsor $sponsor): Sponsor
{
    EnrichSponsor::dispatch($sponsor);

    return $sponsor->refresh();
}

function sentToBrave(): int
{
    return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://api.search.brave.com/'))->count();
}

const ACME_RESULTS = [
    ['url' => 'https://uk.linkedin.com/company/acme-software', 'title' => 'Acme Software | LinkedIn'],
    ['url' => 'https://www.acmesoftware.co.uk/about', 'title' => 'About Acme Software'],
];

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config([
        'sponsor-finder.companies_house_key' => 'ch-key',
        'sponsor-finder.brave_key' => 'brave-key',
    ]);
    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function addresses(string $host): array
        {
            return ['93.184.215.14'];
        }
    });
});

it('finds the company, classifies it, and saves the website and confidence', function () {
    fakeApis(results: ACME_RESULTS);

    $sponsor = enrich(acme());

    expect($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->skip_reason)->toBeNull()
        ->and($sponsor->company_number)->toBe('01234567')
        ->and($sponsor->company_status)->toBe('active')
        ->and($sponsor->sic_codes)->toBe(['62012', '62020'])
        ->and($sponsor->is_tech)->toBeTrue()
        ->and($sponsor->tech_reason)->toBe(TechReason::Sic)
        ->and($sponsor->website)->toBe('https://www.acmesoftware.co.uk')
        // 60 for the name in the host, 15 for the title, 25 for the name on the page.
        ->and($sponsor->confidence)->toBe(100)
        ->and($sponsor->attempts)->toBe(1)
        ->and($sponsor->error)->toBeNull();
});

it('searches for the original name and town', function () {
    fakeApis(results: ACME_RESULTS);

    enrich(acme());

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.search.brave.com/')
        && $request['q'] === 'Acme Software Ltd Sheffield');
});

it('saves a lower confidence when the homepage does not name the company', function () {
    fakeApis(results: ACME_RESULTS, homepage: '<p>Welcome</p>');

    expect(enrich(acme())->confidence)->toBe(75);
});

it('is done with no website when no search result scores', function () {
    fakeApis(results: [['url' => 'https://uk.linkedin.com/company/acme-software', 'title' => 'Acme Software']]);

    $sponsor = enrich(acme());

    expect($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->website)->toBeNull()
        ->and($sponsor->confidence)->toBeNull()
        ->and($sponsor->skip_reason)->toBeNull();
});

it('skips a sponsor with no confident Companies House match', function () {
    Http::fake([ENRICH_CH_SEARCH => Http::response(enrichFixture('companies-house/search-no-match'))]);

    $sponsor = enrich(acme());

    expect($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::NoMatch)
        ->and($sponsor->company_number)->toBeNull()
        ->and($sponsor->attempts)->toBe(1);
    Http::assertSentCount(1);
});

it('skips a company that is not active, without searching', function (?string $status) {
    fakeApis(['company_status' => $status], ACME_RESULTS);

    $sponsor = enrich(acme());

    expect($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::Inactive)
        ->and($sponsor->company_status)->toBe($status)
        ->and($sponsor->is_tech)->toBeTrue()
        ->and(sentToBrave())->toBe(0);
})->with(['dissolved', 'liquidation', null]);

it('skips a company that is not tech, without searching', function () {
    Http::fake([
        ENRICH_CH_SEARCH => Http::response(['items' => [['title' => 'ACME KITCHENS LIMITED', 'company_number' => '01234567']]]),
        ENRICH_CH_PROFILE => Http::response(['company_number' => '01234567', 'company_status' => 'active', 'sic_codes' => ['56101']]),
        ENRICH_BRAVE => Http::response(['web' => ['results' => ACME_RESULTS]]),
    ]);

    $sponsor = enrich(acme(['name' => 'Acme Kitchens Ltd']));

    expect($sponsor->status)->toBe(SponsorStatus::Skipped)
        ->and($sponsor->skip_reason)->toBe(SkipReason::NotTech)
        ->and($sponsor->is_tech)->toBeFalse()
        ->and($sponsor->tech_reason)->toBeNull()
        ->and($sponsor->sic_codes)->toBe(['56101'])
        ->and(sentToBrave())->toBe(0);
});

it('takes a sponsor tagged by name keyword, with no tech SIC code, to done', function () {
    Http::fake([
        ENRICH_CH_SEARCH => Http::response(['items' => [['title' => 'ACME DIGITAL LIMITED', 'company_number' => '01234567']]]),
        ENRICH_CH_PROFILE => Http::response(['company_number' => '01234567', 'company_status' => 'active', 'sic_codes' => ['73110']]),
        ENRICH_BRAVE => Http::response(['web' => ['results' => [['url' => 'https://acmedigital.co.uk/', 'title' => 'Acme Digital']]]]),
        'https://acmedigital.co.uk' => Http::response('<h1>Acme Digital</h1>'),
    ]);

    $sponsor = enrich(acme(['name' => 'Acme Digital Ltd']));

    expect($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->tech_reason)->toBe(TechReason::Keyword)
        ->and($sponsor->is_tech)->toBeTrue()
        ->and($sponsor->website)->toBe('https://acmedigital.co.uk')
        ->and($sponsor->confidence)->toBe(100);
});

it('carries on from ch_done without calling Companies House again', function () {
    fakeApis(results: ACME_RESULTS);

    $sponsor = enrich(acme([
        'status' => SponsorStatus::ChDone,
        'company_number' => '01234567',
        'company_status' => 'active',
        'sic_codes' => ['62012'],
        'is_tech' => true,
        'tech_reason' => TechReason::Sic,
        'attempts' => 1,
    ]));

    expect($sponsor->status)->toBe(SponsorStatus::Done)
        ->and($sponsor->website)->toBe('https://www.acmesoftware.co.uk')
        ->and($sponsor->attempts)->toBe(2);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'company-information.service.gov.uk'));
});

it('changes nothing when run twice on a done row', function () {
    fakeApis(results: ACME_RESULTS);
    $sponsor = enrich(acme());
    $before = $sponsor->getAttributes();
    $sent = Http::recorded()->count();

    $sponsor = enrich($sponsor);

    expect($sponsor->getAttributes())->toBe($before)
        ->and(Http::recorded()->count())->toBe($sent);
});

it('leaves skipped and failed rows alone', function (SponsorStatus $status, ?SkipReason $reason) {
    Http::fake();

    $sponsor = enrich(acme(['status' => $status, 'skip_reason' => $reason, 'attempts' => 3]));

    expect($sponsor->status)->toBe($status)
        ->and($sponsor->skip_reason)->toBe($reason)
        ->and($sponsor->attempts)->toBe(3);
    Http::assertNothingSent();
})->with([
    'skipped' => [SponsorStatus::Skipped, SkipReason::BRating],
    'failed' => [SponsorStatus::Failed, null],
]);

it('marks the sponsor failed and saves the error after the final retry', function () {
    Http::fake([ENRICH_CH_SEARCH => Http::response('', 500)]);
    $sponsor = acme();

    expect(fn () => EnrichSponsor::dispatch($sponsor))->toThrow(CompaniesHouseRequestFailed::class);

    $sponsor->refresh();
    expect($sponsor->status)->toBe(SponsorStatus::Failed)
        ->and($sponsor->error)->toBe('Companies House request failed: /search/companies returned HTTP 500.')
        ->and($sponsor->skip_reason)->toBeNull()
        ->and($sponsor->attempts)->toBe(1);
});

it('keeps the Companies House results when the search fails', function () {
    Http::fake([
        ENRICH_CH_SEARCH => Http::response(enrichFixture('companies-house/search-match')),
        ENRICH_CH_PROFILE => Http::response(enrichFixture('companies-house/profile')),
        ENRICH_BRAVE => Http::response('', 401),
    ]);
    $sponsor = acme();

    expect(fn () => EnrichSponsor::dispatch($sponsor))->toThrow(WebSearchRequestFailed::class);

    $sponsor->refresh();
    expect($sponsor->status)->toBe(SponsorStatus::Failed)
        ->and($sponsor->error)->toBe('Web search failed: Brave returned HTTP 401.')
        ->and($sponsor->company_number)->toBe('01234567')
        ->and($sponsor->is_tech)->toBeTrue();
});

it('caps the saved error at 500 characters', function () {
    $sponsor = acme();

    (new EnrichSponsor($sponsor))->failed(new RuntimeException(str_repeat('x', 600)));

    expect(mb_strlen((string) $sponsor->refresh()->error))->toBe(500)
        ->and($sponsor->status)->toBe(SponsorStatus::Failed);
});

it('fails after three errors, with the spec backoff, and does not count rate limit waits', function () {
    $job = new EnrichSponsor(acme());

    expect($job->maxExceptions)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900])
        ->and(property_exists($job, 'tries'))->toBeFalse()
        ->and($job->retryUntil()->isAfter(now()->addHours(23)))->toBeTrue();
});

it('uses the shared external-apis rate limiter from config', function () {
    config(['sponsor-finder.rate_per_minute' => 12]);

    $middleware = (new EnrichSponsor(acme()))->middleware();
    $limit = RateLimiter::limiter('external-apis')(new EnrichSponsor(acme()));

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
        ->and($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(12)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('');
});
