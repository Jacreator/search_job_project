<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Fetches a web page safely: http or https only, every host checked for a public address,
 * redirects followed by hand (each hop checked), a 10 second timeout, and a download cap.
 */
final class PageFetcher
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_REDIRECTS = 5;

    private const MAX_DOWNLOAD_BYTES = 2_097_152;

    public function __construct(
        private readonly HostResolver $resolver,
    ) {}

    /**
     * The final response after redirects, or null when the address is not allowed, a redirect
     * goes somewhere not allowed or too far, or the request fails.
     */
    public function get(string $url): ?FetchedPage
    {
        try {
            return $this->follow($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function follow(string $url): ?FetchedPage
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

            return new FetchedPage($url, $response->status(), $response->body());
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
}
