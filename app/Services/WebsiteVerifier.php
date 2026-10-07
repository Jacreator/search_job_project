<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CompanyName;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Loads a homepage, checks it for the company name, and returns the page text.
 */
final class WebsiteVerifier
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_REDIRECTS = 5;

    private const MAX_DOWNLOAD_BYTES = 2_097_152;

    private const MAX_TEXT_BYTES = 204_800;

    public function __construct(
        private readonly HostResolver $resolver,
    ) {}

    public function verify(string $url, string $name): VerifiedPage
    {
        try {
            $html = $this->fetch($url);
        } catch (Throwable) {
            return VerifiedPage::failed();
        }

        if ($html === null) {
            return VerifiedPage::failed();
        }

        $text = self::pageText($html);
        $firstWord = explode(' ', CompanyName::normalise($name))[0];

        return new VerifiedPage($firstWord !== '' && self::containsWord($text, $firstWord), $text);
    }

    /**
     * Follow redirects by hand, so every hop is checked for a public address before it is fetched.
     */
    private function fetch(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $parts = parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

            if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
                return null;
            }

            $address = $this->publicAddress($host);

            if ($address === null) {
                return null;
            }

            $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            $response = $this->request($url, "{$host}:{$port}:{$address}");

            if ($response->redirect()) {
                $url = self::resolveLocation($url, $response->header('Location'));

                continue;
            }

            return $response->successful() ? $response->body() : null;
        }

        return null;
    }

    private function request(string $url, string $pinnedAddress): Response
    {
        return Http::withoutRedirecting()
            ->accept('text/html,application/xhtml+xml')
            ->timeout(self::TIMEOUT_SECONDS)
            ->withOptions([
                // Connect to the address that was checked, so DNS cannot change between the check and the request.
                'curl' => [CURLOPT_RESOLVE => [$pinnedAddress]],
                // Abort a page bigger than the download cap. The fetch then counts as failed.
                'progress' => function (int $expected, int $downloaded): void {
                    if ($expected > self::MAX_DOWNLOAD_BYTES || $downloaded > self::MAX_DOWNLOAD_BYTES) {
                        throw new RuntimeException('Page is larger than the download cap.');
                    }
                },
            ])
            ->retry(2, 500, fn (?Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
            ->get($url);
    }

    /**
     * The host's first address, or null when it has none or any address is private, local, or reserved.
     */
    private function publicAddress(string $host): ?string
    {
        $addresses = $this->resolver->addresses($host);

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $addresses[0] ?? null;
    }

    private static function resolveLocation(string $current, string $location): string
    {
        $location = trim($location);

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($current);
        $scheme = (string) ($parts['scheme'] ?? 'https');

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ":{$parts['port']}" : '');

        return $origin.'/'.ltrim($location, '/');
    }

    private static function pageText(string $html): string
    {
        $html = mb_scrub($html, 'UTF-8');
        $html = (string) preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>|<!--.*?-->#is', ' ', $html);
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));

        return mb_strcut($text, 0, self::MAX_TEXT_BYTES, 'UTF-8');
    }

    /**
     * Whole-word match. Apostrophes are dropped first, as `CompanyName` does ("sainsbury's" holds "sainsburys").
     */
    private static function containsWord(string $text, string $word): bool
    {
        $text = str_replace(["'", "\u{2019}"], '', $text);

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $text) === 1;
    }
}
