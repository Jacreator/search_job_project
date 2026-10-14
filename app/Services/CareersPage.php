<?php

declare(strict_types=1);

namespace App\Services;

final readonly class CareersPage
{
    /**
     * @param  string  $text  Stripped, lowercased page text, capped at 200 KB.
     */
    public function __construct(
        public string $url,
        public string $text,
    ) {}
}
