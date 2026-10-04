<?php

declare(strict_types=1);

namespace App\Enums;

enum SkipReason: string
{
    case NoMatch = 'no_match';
    case NotTech = 'not_tech';
    case Inactive = 'inactive';
    case BRating = 'b_rating';
}
