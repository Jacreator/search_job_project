<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Makes untrusted text safe to open in a spreadsheet.
 */
final class CsvCell
{
    private const FORMULA_STARTS = ['=', '+', '-', '@'];

    /**
     * Prefix a cell that a spreadsheet would run as a formula with an apostrophe, so it stays text.
     */
    public static function safe(?string $value): string
    {
        $value ??= '';

        if ($value !== '' && in_array($value[0], self::FORMULA_STARTS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
