<?php

declare(strict_types=1);

namespace App\Services;

final readonly class WebsiteCandidate
{
    public function __construct(
        public string $url,
        public int $score,
    ) {}
}
