<?php

declare(strict_types=1);

namespace App\Services;

final readonly class VerifiedPage
{
    /**
     * @param  string  $text  Stripped, lowercased page text, capped at 200 KB. Empty when the fetch failed.
     */
    public function __construct(
        public bool $mentionsName,
        public string $text,
    ) {}

    public static function failed(): self
    {
        return new self(false, '');
    }
}
