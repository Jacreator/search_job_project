<?php

declare(strict_types=1);

namespace App\Services;

final readonly class CompanyProfile
{
    /**
     * @param  list<string>  $sicCodes
     */
    public function __construct(
        public string $number,
        public ?string $status,
        public array $sicCodes,
    ) {}
}
