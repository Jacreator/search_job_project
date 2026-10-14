<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one place term matching happens. Case-insensitive, whole words or phrases, an optional
 * plural "s", hyphens as spaces, and curly apostrophes as straight ones. Pure: no database or HTTP.
 */
final class TermMatcher
{
    /**
     * Whether the text contains any of the terms.
     *
     * @param  array<int, string>  $terms
     */
    public static function contains(string $text, array $terms): bool
    {
        $text = self::prepare($text);

        if ($text === '') {
            return false;
        }

        $patterns = [];

        foreach ($terms as $term) {
            $term = self::prepare($term);

            if ($term !== '') {
                $patterns[] = preg_quote($term, '/');
            }
        }

        if ($patterns === []) {
            return false;
        }

        // A letter, digit, or underscore on either side means the term is part of a longer word.
        $pattern = '/(?<![\p{L}\p{N}_])(?:'.implode('|', $patterns).')s?(?![\p{L}\p{N}_])/u';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * Lowercase, straight apostrophes, hyphens as spaces, and single spaces.
     */
    private static function prepare(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(["\u{2019}", "\u{2018}"], "'", $value);
        $value = str_replace(['-', "\u{2010}", "\u{2011}"], ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
