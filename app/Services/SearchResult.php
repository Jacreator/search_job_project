<?php

declare(strict_types=1);

namespace App\Services;

final readonly class SearchResult
{
    public function __construct(
        public string $url,
        public string $title,
    ) {}
}
