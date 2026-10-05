<?php

declare(strict_types=1);

use App\Exceptions\CompaniesHouseRequestFailed;
use App\Services\CompaniesHouse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

const CH_SEARCH = 'https://api.company-information.service.gov.uk/search/companies*';
const CH_PROFILE = 'https://api.company-information.service.gov.uk/company/01234567';

/**
 * @return array<string, mixed>
 */
function chFixture(string $name): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/companies-house/{$name}.json")), true);
}

function companiesHouse(): CompaniesHouse
{
    return app(CompaniesHouse::class);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config(['sponsor-finder.companies_house_key' => 'test-key']);
});

it('finds the result whose normalised title matches the sponsor name', function () {
    Http::fake([CH_SEARCH => Http::response(chFixture('search-match'))]);

    $match = companiesHouse()->findCompany('Acme Software Ltd');

    expect($match)->not->toBeNull()
        ->and($match->number)->toBe('01234567')
        ->and($match->title)->toBe('ACME SOFTWARE LIMITED');
});

it('searches by name for five results with the key as the basic auth username', function () {
    Http::fake([CH_SEARCH => Http::response(chFixture('search-match'))]);

    companiesHouse()->findCompany('Acme Software Ltd');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.company-information.service.gov.uk/search/companies?')
        && $request['q'] === 'Acme Software Ltd'
        && (int) $request['items_per_page'] === 5
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:')));
});

it('matches a sponsor that trades under another name', function () {
    Http::fake([CH_SEARCH => Http::response(chFixture('search-match'))]);

    expect(companiesHouse()->findCompany('Acme Software Limited t/a Acme Apps')?->number)->toBe('01234567');
});

it('returns null when no result matches the sponsor name', function () {
    Http::fake([CH_SEARCH => Http::response(chFixture('search-no-match'))]);

    expect(companiesHouse()->findCompany('Acme Software Ltd'))->toBeNull();
});

it('returns null when the search has no results', function (array $body) {
    Http::fake([CH_SEARCH => Http::response($body)]);

    expect(companiesHouse()->findCompany('Acme Software Ltd'))->toBeNull();
})->with([
    'empty items' => [['items' => []]],
    'no items key' => [['total_results' => 0]],
]);

it('skips results that are missing a title or company number', function () {
    Http::fake([CH_SEARCH => Http::response(['items' => [
        ['title' => 'ACME SOFTWARE LIMITED'],
        ['company_number' => '44444444'],
        ['title' => 'ACME SOFTWARE LIMITED', 'company_number' => ''],
        'not an item',
    ]])]);

    expect(companiesHouse()->findCompany('Acme Software Ltd'))->toBeNull();
});

it('does not search for a name that normalises to nothing', function () {
    Http::fake();

    expect(companiesHouse()->findCompany('The Group Ltd'))->toBeNull();
    Http::assertNothingSent();
});

it('throws when the search fails, without the API key in the message', function () {
    Http::fake([CH_SEARCH => Http::response('Server error', 500)]);

    try {
        companiesHouse()->findCompany('Acme Software Ltd');
        $this->fail('Expected an exception.');
    } catch (CompaniesHouseRequestFailed $exception) {
        expect($exception->getMessage())->toBe('Companies House request failed: /search/companies returned HTTP 500.')
            ->not->toContain('test-key');
    }
});

it('retries rate limits and server errors once before giving up', function (int $status) {
    Http::fake([CH_SEARCH => Http::response('', $status)]);

    expect(fn () => companiesHouse()->findCompany('Acme Software Ltd'))
        ->toThrow(CompaniesHouseRequestFailed::class, "returned HTTP {$status}");
    Http::assertSentCount(2);
})->with([429, 500, 503]);

it('recovers when a retry succeeds', function () {
    Http::fakeSequence(CH_SEARCH)
        ->push('', 503)
        ->push(chFixture('search-match'));

    expect(companiesHouse()->findCompany('Acme Software Ltd')?->number)->toBe('01234567');
    Http::assertSentCount(2);
});

it('does not retry a rejected key', function () {
    Http::fake([CH_SEARCH => Http::response(['errors' => [['error' => 'invalid-authorization-header']]], 401)]);

    expect(fn () => companiesHouse()->findCompany('Acme Software Ltd'))
        ->toThrow(CompaniesHouseRequestFailed::class, 'returned HTTP 401');
    Http::assertSentCount(1);
});

it('throws when Companies House cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => companiesHouse()->findCompany('Acme Software Ltd'))
        ->toThrow(CompaniesHouseRequestFailed::class, 'could not connect for /search/companies');
});

it('throws without calling the API when no key is set', function (?string $key) {
    config(['sponsor-finder.companies_house_key' => $key]);
    Http::fake();

    expect(fn () => companiesHouse()->findCompany('Acme Software Ltd'))
        ->toThrow(CompaniesHouseRequestFailed::class, 'COMPANIES_HOUSE_KEY is not set');
    Http::assertNothingSent();
})->with([null, '']);

it('throws without calling the API when the key contains a control character', function () {
    config(['sponsor-finder.companies_house_key' => "test\tkey"]);
    Http::fake();

    expect(fn () => companiesHouse()->findCompany('Acme Software Ltd'))
        ->toThrow(CompaniesHouseRequestFailed::class, 'COMPANIES_HOUSE_KEY contains control characters');
    Http::assertNothingSent();
});

it('calls the base URL from config', function () {
    config(['sponsor-finder.companies_house_url' => 'https://ch.example.test']);
    Http::fake(['https://ch.example.test/*' => Http::response(chFixture('search-match'))]);

    expect(companiesHouse()->findCompany('Acme Software Ltd')?->number)->toBe('01234567');
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://ch.example.test/search/companies?'));
});

it('gets the company status and SIC codes', function () {
    Http::fake([CH_PROFILE => Http::response(chFixture('profile'))]);

    $profile = companiesHouse()->profile('01234567');

    expect($profile->number)->toBe('01234567')
        ->and($profile->status)->toBe('active')
        ->and($profile->sicCodes)->toBe(['62012', '62020']);
});

it('treats a profile with no SIC codes or status as empty', function () {
    Http::fake([CH_PROFILE => Http::response(['company_number' => '01234567'])]);

    $profile = companiesHouse()->profile('01234567');

    expect($profile->status)->toBeNull()
        ->and($profile->sicCodes)->toBe([]);
});

it('keeps only string SIC codes', function () {
    Http::fake([CH_PROFILE => Http::response([
        'company_number' => '01234567',
        'company_status' => 'dissolved',
        'sic_codes' => ['62012', 62020, null],
    ])]);

    $profile = companiesHouse()->profile('01234567');

    expect($profile->status)->toBe('dissolved')
        ->and($profile->sicCodes)->toBe(['62012']);
});

it('throws when the profile is not found, without retrying', function () {
    Http::fake([CH_PROFILE => Http::response(['errors' => [['error' => 'company-profile-not-found']]], 404)]);

    expect(fn () => companiesHouse()->profile('01234567'))
        ->toThrow(CompaniesHouseRequestFailed::class, '/company/01234567 returned HTTP 404');
    Http::assertSentCount(1);
});

it('throws when the profile response has no company number', function () {
    Http::fake([CH_PROFILE => Http::response(['company_status' => 'active'])]);

    expect(fn () => companiesHouse()->profile('01234567'))
        ->toThrow(CompaniesHouseRequestFailed::class, 'returned an unexpected response');
});

it('escapes the company number in the profile URL', function () {
    Http::fake(['https://api.company-information.service.gov.uk/company/*' => Http::response(chFixture('profile'))]);

    companiesHouse()->profile('01/234 567');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.company-information.service.gov.uk/company/01%2F234%20567');
});
