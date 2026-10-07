<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Messages are saved on failed sponsors, so they never include the API key, the query, or request headers.
 */
final class WebSearchRequestFailed extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self('Web search failed: BRAVE_SEARCH_KEY is not set.');
    }

    public static function invalidKey(): self
    {
        return new self('Web search failed: BRAVE_SEARCH_KEY contains control characters.');
    }

    public static function status(int $status): self
    {
        return new self("Web search failed: Brave returned HTTP {$status}.");
    }

    public static function connection(): self
    {
        return new self('Web search failed: could not connect to Brave.');
    }
}
