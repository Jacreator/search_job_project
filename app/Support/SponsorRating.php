<?php

declare(strict_types=1);

namespace App\Support;

final class SponsorRating
{
    private const GRADES = [
        'a rating' => 'A',
        'a (premium)' => 'A',
        'a (sme+)' => 'A',
        'b rating' => 'B',
    ];

    /**
     * Parse the grade from a register "Type & Rating" value, such as
     * "Worker (A rating)" or "Temporary Worker (A (Premium))".
     *
     * @return 'A'|'B'|null
     */
    public static function grade(string $value): ?string
    {
        $value = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        if (preg_match('/^(?:temporary )?worker \((.*)\)$/', $value, $matches) === 1) {
            $value = trim($matches[1]);
        }

        return self::GRADES[$value] ?? null;
    }
}
