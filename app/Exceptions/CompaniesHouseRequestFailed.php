<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Messages are saved on failed sponsors, so they never include the API key or request headers.
 */
final class CompaniesHouseRequestFailed extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self('Companies House request failed: COMPANIES_HOUSE_KEY is not set.');
    }

    public static function invalidKey(): self
    {
        return new self('Companies House request failed: COMPANIES_HOUSE_KEY contains control characters.');
    }

    public static function status(string $endpoint, int $status): self
    {
        return new self("Companies House request failed: {$endpoint} returned HTTP {$status}.");
    }

    public static function connection(string $endpoint): self
    {
        return new self("Companies House request failed: could not connect for {$endpoint}.");
    }

    public static function invalidResponse(string $endpoint): self
    {
        return new self("Companies House request failed: {$endpoint} returned an unexpected response.");
    }
}
