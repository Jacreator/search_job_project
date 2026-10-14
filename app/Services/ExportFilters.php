<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VisaSponsorship;

final readonly class ExportFilters
{
    public function __construct(
        public int $minConfidence = 50,
        public ?string $town = null,
        public ?string $region = null,
        public bool $confirmedOnly = false,
        public bool $scaleUpOnly = false,
        public bool $developerRolesOnly = false,
        public ?VisaSponsorship $visaSponsorship = null,
    ) {}
}
