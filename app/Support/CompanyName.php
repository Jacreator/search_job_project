<?php

declare(strict_types=1);

namespace App\Support;

final class CompanyName
{
    private const REMOVED_WORDS = ['limited', 'ltd', 'plc', 'llp', 'uk', 'the', 'group', 'holdings'];

    /**
     * Normalise a company name for matching: lowercase, drop any trading name,
     * remove common suffixes and filler words, and remove punctuation.
     */
    public static function normalise(string $name): string
    {
        $name = mb_strtolower($name);

        // Drop the trading name: "Acme Ltd t/a Widgets" (or "t/as", "t/a's") is matched as "Acme Ltd".
        $name = (string) preg_replace('/(?<![\p{L}\p{N}])(t\s*\/\s*a(?:s|[\'\x{2019}]s)?|trading\s+as)(?![\p{L}\p{N}]).*$/us', '', $name);

        // Apostrophes join their word ("sainsbury's" becomes "sainsburys"), other punctuation splits words.
        $name = str_replace(["'", "\u{2019}"], '', $name);
        $name = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

        $words = array_filter(
            explode(' ', $name),
            fn (string $word): bool => $word !== '' && ! in_array($word, self::REMOVED_WORDS, true),
        );

        return implode(' ', $words);
    }
}
