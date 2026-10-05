<?php

declare(strict_types=1);

namespace App\Services;

final readonly class CompanyMatch
{
    public function __construct(
        public string $number,
        public string $title,
    ) {}
}
