<?php

declare(strict_types=1);

use App\Services\HostResolver;
use App\Services\WebsiteVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, list<string>>  $addresses  host => IPv4 addresses. Unlisted hosts get a public address.
 */
function fakeHosts(array $addresses = []): void
{
    app()->instance(HostResolver::class, new class($addresses) extends HostResolver
    {
        /**
         * @param  array<string, list<string>>  $addresses
         */
        public function __construct(private readonly array $addresses) {}

        public function addresses(string $host): array
        {
            return $this->addresses[$host] ?? ['93.184.215.14'];
        }
    });
}

function verifier(): WebsiteVerifier
{
    return app(WebsiteVerifier::class);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
    fakeHosts();
});

it('finds the name on the homepage and returns the page text', function () {
    Http::fake(['https://fourjaw.com' => Http::response((string) file_get_contents(base_path('tests/Fixtures/websites/fourjaw.html')))]);

    $page = verifier()->verify('https://fourjaw.com', 'FourJaw Manufacturing Analytics Ltd');

    expect($page->mentionsName)->toBeTrue()
        ->and($page->text)->toContain('fourjaw helps manufacturers in sheffield')
        ->toContain('machine monitoring & analytics')
        ->not->toContain('<')
        ->not->toContain('hidden-script-word')
        ->not->toContain('hidden-comment-word')
        ->not->toContain('.hero');
});

it('does not find the name when the page does not mention it', function () {
    Http::fake(['https://fourjaw.com' => Http::response('<h1>Machine monitoring</h1><p>Fourjaws and more</p>')]);

    $page = verifier()->verify('https://fourjaw.com', 'FourJaw Ltd');

    expect($page->mentionsName)->toBeFalse()
        ->and($page->text)->toBe('machine monitoring fourjaws and more');
});

it('looks for the first word of the normalised name', function () {
    Http::fake(['https://www.thefloow.com' => Http::response("<p>Welcome to Floow\u{2019}s home</p>")]);

    expect(verifier()->verify('https://www.thefloow.com', 'The Floow Limited')->mentionsName)->toBeFalse();

    Http::fake(['https://www.sainsburys.co.uk' => Http::response("<p>Sainsbury\u{2019}s supermarkets</p>")]);

    expect(verifier()->verify('https://www.sainsburys.co.uk', "Sainsbury's Supermarkets Ltd")->mentionsName)->toBeTrue();
});

it('returns a failed page when the request errors', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $page = verifier()->verify('https://fourjaw.com', 'FourJaw Ltd');

    expect($page->mentionsName)->toBeFalse()
        ->and($page->text)->toBe('');
});

it('returns a failed page for an error status', function (int $status) {
    Http::fake(['https://fourjaw.com' => Http::response('<p>FourJaw</p>', $status)]);

    expect(verifier()->verify('https://fourjaw.com', 'FourJaw Ltd'))
        ->mentionsName->toBeFalse()
        ->text->toBe('');
})->with([404, 500]);

it('fetches the homepage asking for HTML', function () {
    Http::fake(['https://fourjaw.com' => Http::response('<p>FourJaw</p>')]);

    verifier()->verify('https://fourjaw.com', 'FourJaw Ltd');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://fourjaw.com'
        && $request->hasHeader('Accept', 'text/html,application/xhtml+xml'));
});

it('caps the text at 200 KB', function () {
    Http::fake(['https://fourjaw.com' => Http::response('<p>FourJaw '.str_repeat('é', 150_000).'</p>')]);

    $page = verifier()->verify('https://fourjaw.com', 'FourJaw Ltd');

    expect(strlen($page->text))->toBeLessThanOrEqual(204_800)
        ->toBeGreaterThan(204_790)
        ->and(mb_check_encoding($page->text, 'UTF-8'))->toBeTrue()
        ->and($page->mentionsName)->toBeTrue();
});

it('follows a redirect to another public page', function () {
    Http::fake([
        'http://fourjaw.com' => Http::response('', 301, ['Location' => 'https://fourjaw.com/']),
        'https://fourjaw.com/' => Http::response('<p>FourJaw</p>'),
    ]);

    expect(verifier()->verify('http://fourjaw.com', 'FourJaw Ltd')->mentionsName)->toBeTrue();
    Http::assertSentCount(2);
});

it('follows a relative redirect', function () {
    Http::fake([
        'https://fourjaw.com' => Http::response('', 302, ['Location' => '/en/home']),
        'https://fourjaw.com/en/home' => Http::response('<p>FourJaw</p>'),
    ]);

    expect(verifier()->verify('https://fourjaw.com', 'FourJaw Ltd')->mentionsName)->toBeTrue();
});

it('does not follow a redirect to a private or local address', function (string $address) {
    fakeHosts(['internal.example' => [$address]]);
    Http::fake([
        'https://fourjaw.com' => Http::response('', 302, ['Location' => 'http://internal.example/admin']),
        'http://internal.example/*' => Http::response('<p>FourJaw</p>'),
    ]);

    expect(verifier()->verify('https://fourjaw.com', 'FourJaw Ltd'))
        ->mentionsName->toBeFalse()
        ->text->toBe('');
    Http::assertSentCount(1);
})->with(['127.0.0.1', '10.0.0.5', '192.168.1.10', '172.16.0.1', '169.254.169.254', '0.0.0.0']);

it('does not fetch a host with a private address or no address', function (array $addresses) {
    fakeHosts(['fourjaw.com' => $addresses]);
    Http::fake();

    expect(verifier()->verify('https://fourjaw.com', 'FourJaw Ltd')->text)->toBe('');
    Http::assertNothingSent();
})->with([
    'private' => [['10.0.0.5']],
    'one of several private' => [['93.184.215.14', '127.0.0.1']],
    'none' => [[]],
]);

it('does not fetch a URL that is not http or https', function (string $url) {
    Http::fake();

    expect(verifier()->verify($url, 'FourJaw Ltd')->text)->toBe('');
    Http::assertNothingSent();
})->with(['file:///etc/passwd', 'ftp://fourjaw.com/', 'fourjaw.com']);

it('does not follow a redirect to a non-web scheme', function () {
    Http::fake(['https://fourjaw.com' => Http::response('', 302, ['Location' => 'file:///etc/passwd'])]);

    expect(verifier()->verify('https://fourjaw.com', 'FourJaw Ltd')->text)->toBe('');
    Http::assertSentCount(1);
});

it('stops after five redirects', function () {
    Http::fake(['https://fourjaw.com/*' => Http::response('', 302, ['Location' => 'https://fourjaw.com/loop'])]);

    expect(verifier()->verify('https://fourjaw.com/start', 'FourJaw Ltd')->text)->toBe('');
    Http::assertSentCount(6);
});
