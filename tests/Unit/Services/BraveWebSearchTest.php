<?php

declare(strict_types=1);

use App\Contracts\WebSearch;
use App\Exceptions\WebSearchRequestFailed;
use App\Services\BraveWebSearch;
use App\Services\SearchResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

const BRAVE_SEARCH = 'https://api.search.brave.com/res/v1/web/search*';

/**
 * @return array<string, mixed>
 */
function braveFixture(string $name): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/brave/{$name}.json")), true);
}

function webSearch(): WebSearch
{
    return app(WebSearch::class);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    config(['sponsor-finder.brave_key' => 'test-key']);
});

it('binds the web search interface to Brave', function () {
    expect(webSearch())->toBeInstanceOf(BraveWebSearch::class);
});

it('returns the parsed results in order', function () {
    Http::fake([BRAVE_SEARCH => Http::response(braveFixture('search-results'))]);

    $results = webSearch()->search('Acme Software Ltd Sheffield');

    expect($results)->toHaveCount(3)
        ->each->toBeInstanceOf(SearchResult::class)
        ->and($results[0]->url)->toBe('https://www.acmesoftware.co.uk/')
        ->and($results[0]->title)->toBe('Acme Software | Bespoke software in Sheffield')
        ->and($results[2]->url)->toBe('https://uk.linkedin.com/company/acme-software');
});

it('searches UK results, ten at a time, with the key in the subscription header', function () {
    Http::fake([BRAVE_SEARCH => Http::response(braveFixture('search-results'))]);

    webSearch()->search('Acme Software Ltd Sheffield');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://api.search.brave.com/res/v1/web/search?')
        && $request['q'] === 'Acme Software Ltd Sheffield'
        && $request['country'] === 'gb'
        && (int) $request['count'] === 10
        && $request->hasHeader('X-Subscription-Token', 'test-key'));
});

it('returns an empty list when there are no results', function (array $body) {
    Http::fake([BRAVE_SEARCH => Http::response($body)]);

    expect(webSearch()->search('Unknown Widgets Ltd Sheffield'))->toBe([]);
})->with([
    'no web key' => [fn () => braveFixture('search-empty')],
    'empty results' => [['web' => ['results' => []]]],
    'no results key' => [['web' => ['type' => 'search']]],
    'empty body' => [[]],
]);

it('returns an empty list for a malformed response', function (mixed $body) {
    Http::fake([BRAVE_SEARCH => Http::response($body)]);

    expect(webSearch()->search('Acme Software Ltd Sheffield'))->toBe([]);
})->with([
    'not JSON' => ['<html>Oops</html>'],
    'web is a string' => [['web' => 'results']],
    'results is a string' => [['web' => ['results' => 'none']]],
    'JSON string' => ['"results"'],
]);

it('skips results without a usable URL or title', function () {
    Http::fake([BRAVE_SEARCH => Http::response(['web' => ['results' => [
        ['title' => 'No URL'],
        ['url' => 'https://no-title.example.com/'],
        ['title' => 'Not a web link', 'url' => 'ftp://files.example.com/'],
        ['title' => 'Relative', 'url' => '/acme'],
        ['title' => 123, 'url' => 'https://number-title.example.com/'],
        ['title' => 'Acme', 'url' => ['https://array.example.com/']],
        'not a result',
        ['title' => 'Acme Software', 'url' => 'https://www.acmesoftware.co.uk/'],
    ]]])]);

    $results = webSearch()->search('Acme Software Ltd Sheffield');

    expect($results)->toHaveCount(1)
        ->and($results[0]->url)->toBe('https://www.acmesoftware.co.uk/');
});

it('throws when the search fails, without the key or query in the message', function () {
    Http::fake([BRAVE_SEARCH => Http::response('Server error', 500)]);

    try {
        webSearch()->search('Acme Software Ltd Sheffield');
        $this->fail('Expected an exception.');
    } catch (WebSearchRequestFailed $exception) {
        expect($exception->getMessage())->toBe('Web search failed: Brave returned HTTP 500.')
            ->not->toContain('test-key')
            ->not->toContain('Acme');
    }
});

it('retries rate limits and server errors once before giving up', function (int $status) {
    Http::fake([BRAVE_SEARCH => Http::response('', $status)]);

    expect(fn () => webSearch()->search('Acme Software Ltd Sheffield'))
        ->toThrow(WebSearchRequestFailed::class, "returned HTTP {$status}");
    Http::assertSentCount(2);
})->with([429, 500, 503]);

it('recovers when a retry succeeds', function () {
    Http::fakeSequence(BRAVE_SEARCH)
        ->push('', 429)
        ->push(braveFixture('search-results'));

    expect(webSearch()->search('Acme Software Ltd Sheffield'))->toHaveCount(3);
    Http::assertSentCount(2);
});

it('does not retry a rejected key', function (int $status) {
    Http::fake([BRAVE_SEARCH => Http::response(['type' => 'ErrorResponse'], $status)]);

    expect(fn () => webSearch()->search('Acme Software Ltd Sheffield'))
        ->toThrow(WebSearchRequestFailed::class, "returned HTTP {$status}");
    Http::assertSentCount(1);
})->with([401, 403, 422]);

it('throws when Brave cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => webSearch()->search('Acme Software Ltd Sheffield'))
        ->toThrow(WebSearchRequestFailed::class, 'could not connect to Brave');
});

it('throws without calling the API when no key is set', function (?string $key) {
    config(['sponsor-finder.brave_key' => $key]);
    Http::fake();

    expect(fn () => webSearch()->search('Acme Software Ltd Sheffield'))
        ->toThrow(WebSearchRequestFailed::class, 'BRAVE_SEARCH_KEY is not set');
    Http::assertNothingSent();
})->with([null, '']);

it('throws without calling the API when the key contains a control character', function () {
    config(['sponsor-finder.brave_key' => "test\tkey"]);
    Http::fake();

    expect(fn () => webSearch()->search('Acme Software Ltd Sheffield'))
        ->toThrow(WebSearchRequestFailed::class, 'BRAVE_SEARCH_KEY contains control characters');
    Http::assertNothingSent();
});
