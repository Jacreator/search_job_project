<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SponsorStatus;

/**
 * The review dashboard's filters. Null or false means the filter is off.
 */
final readonly class SponsorFilters
{
    public const VISA_SPONSORSHIP_VALUES = ['offered', 'not_offered', 'unknown'];

    public function __construct(
        public ?SponsorStatus $status = null,
        public ?string $town = null,
        public ?string $region = null,
        public ?int $minConfidence = null,
        public bool $tech = false,
        public bool $scaleUp = false,
        public bool $ratingMissing = false,
        public bool $needsSecondCheck = false,
        public bool $ai = false,
        public bool $developerRoles = false,
        /** `offered`, `not_offered`, or `unknown` (checked, but the page says nothing either way). */
        public ?string $visaSponsorship = null,
        public ?string $search = null,
    ) {}
}
