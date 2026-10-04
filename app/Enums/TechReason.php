<?php

declare(strict_types=1);

namespace App\Enums;

enum TechReason: string
{
    case Sic = 'sic';
    case Keyword = 'keyword';
    case KnownEmployer = 'known_employer';
}
