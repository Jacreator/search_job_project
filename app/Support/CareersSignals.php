<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\VisaSponsorship;
use Illuminate\Container\Attributes\Config;

/**
 * Reads hiring signals from careers page text. Pure: no database or HTTP.
 */
final readonly class CareersSignals
{
    /**
     * @param  list<string>  $developerRoleTerms
     * @param  list<string>  $visaOfferTerms
     * @param  list<string>  $visaRefusalTerms
     * @param  list<string>  $rightToWorkTerms
     */
    public function __construct(
        #[Config('sponsor-finder.developer_role_terms')] private array $developerRoleTerms,
        #[Config('sponsor-finder.visa_offer_terms')] private array $visaOfferTerms,
        #[Config('sponsor-finder.visa_refusal_terms')] private array $visaRefusalTerms,
        #[Config('sponsor-finder.right_to_work_terms')] private array $rightToWorkTerms,
    ) {}

    public function mentionsDeveloperRoles(string $text): bool
    {
        return TermMatcher::contains($text, $this->developerRoleTerms);
    }

    /**
     * A refusal beats an offer, because pages that say both usually carry a standard
     * "unable to offer visa sponsorship" disclaimer. A right to work line alone counts as not offered.
     */
    public function visaSponsorship(string $text): ?VisaSponsorship
    {
        return match (true) {
            TermMatcher::contains($text, $this->visaRefusalTerms) => VisaSponsorship::NotOffered,
            TermMatcher::contains($text, $this->visaOfferTerms) => VisaSponsorship::Offered,
            TermMatcher::contains($text, $this->rightToWorkTerms) => VisaSponsorship::NotOffered,
            default => null,
        };
    }
}
