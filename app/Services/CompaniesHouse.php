<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CompaniesHouseRequestFailed;
use App\Support\CompanyName;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * All Companies House API calls and name matching.
 */
final class CompaniesHouse
{
    private const SEARCH_RESULTS = 5;

    public function __construct(
        #[Config('sponsor-finder.companies_house_key')] private readonly ?string $key,
        #[Config('sponsor-finder.companies_house_url')] private readonly string $baseUrl,
    ) {}

    /**
     * Find the company whose name matches the sponsor name, or null when no result matches.
     *
     * @throws CompaniesHouseRequestFailed
     */
    public function findCompany(string $name): ?CompanyMatch
    {
        $wanted = CompanyName::normalise($name);

        if ($wanted === '') {
            return null;
        }

        $items = $this->get('/search/companies', [
            'q' => $name,
            'items_per_page' => self::SEARCH_RESULTS,
        ])['items'] ?? [];

        foreach (is_array($items) ? $items : [] as $item) {
            $title = is_array($item) ? ($item['title'] ?? null) : null;
            $number = is_array($item) ? ($item['company_number'] ?? null) : null;

            if (is_string($title) && is_string($number) && $number !== '' && CompanyName::normalise($title) === $wanted) {
                return new CompanyMatch($number, $title);
            }
        }

        return null;
    }

    /**
     * Get the company's status and SIC codes.
     *
     * @throws CompaniesHouseRequestFailed
     */
    public function profile(string $companyNumber): CompanyProfile
    {
        $endpoint = '/company/'.rawurlencode($companyNumber);
        $data = $this->get($endpoint);

        $number = $data['company_number'] ?? null;
        $status = $data['company_status'] ?? null;
        $sicCodes = $data['sic_codes'] ?? [];

        if (! is_string($number) || $number === '') {
            throw CompaniesHouseRequestFailed::invalidResponse($endpoint);
        }

        return new CompanyProfile(
            $number,
            is_string($status) && $status !== '' ? $status : null,
            array_values(array_filter(is_array($sicCodes) ? $sicCodes : [], 'is_string')),
        );
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<mixed>
     *
     * @throws CompaniesHouseRequestFailed
     */
    private function get(string $endpoint, array $query = []): array
    {
        if ($this->key === null || $this->key === '') {
            throw CompaniesHouseRequestFailed::missingKey();
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $this->key) === 1) {
            throw CompaniesHouseRequestFailed::invalidKey();
        }

        try {
            $data = Http::baseUrl($this->baseUrl)
                ->withBasicAuth($this->key, '')
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 500, fn (Throwable $exception): bool => self::shouldRetry($exception))
                ->get($endpoint, $query)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw CompaniesHouseRequestFailed::status($endpoint, $exception->response->status());
        } catch (ConnectionException) {
            throw CompaniesHouseRequestFailed::connection($endpoint);
        }

        if (! is_array($data)) {
            throw CompaniesHouseRequestFailed::invalidResponse($endpoint);
        }

        return $data;
    }

    /**
     * Retry dropped connections, rate limits, and server errors. Never retry a bad key or a missing company.
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
