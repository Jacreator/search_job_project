<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WebSearch;
use App\Exceptions\WebSearchRequestFailed;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * All Brave Search API calls.
 */
final class BraveWebSearch implements WebSearch
{
    private const URL = 'https://api.search.brave.com/res/v1/web/search';

    private const RESULTS = 10;

    public function __construct(
        #[Config('sponsor-finder.brave_key')] private readonly ?string $key,
    ) {}

    public function search(string $query): array
    {
        if ($this->key === null || $this->key === '') {
            throw WebSearchRequestFailed::missingKey();
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $this->key) === 1) {
            throw WebSearchRequestFailed::invalidKey();
        }

        try {
            $data = Http::withHeaders(['X-Subscription-Token' => $this->key])
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 500, fn (Throwable $exception): bool => self::shouldRetry($exception))
                ->get(self::URL, [
                    'q' => $query,
                    'country' => 'gb',
                    'count' => self::RESULTS,
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw WebSearchRequestFailed::status($exception->response->status());
        } catch (ConnectionException) {
            throw WebSearchRequestFailed::connection();
        }

        $items = is_array($data) && is_array($data['web'] ?? null) ? ($data['web']['results'] ?? []) : [];

        $results = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : null;
            $title = is_array($item) ? ($item['title'] ?? null) : null;

            if (is_string($url) && preg_match('#^https?://#i', $url) === 1 && is_string($title)) {
                $results[] = new SearchResult($url, $title);
            }
        }

        return $results;
    }

    /**
     * Retry dropped connections, rate limits, and server errors. Never retry a bad key.
     */
    private static function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->status() === 429 || $exception->response->serverError());
    }
}
