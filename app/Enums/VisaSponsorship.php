<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a careers page says about visa sponsorship. Null means no page, or no wording either way.
 */
enum VisaSponsorship: string
{
    case Offered = 'offered';
    case NotOffered = 'not_offered';
}
