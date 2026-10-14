<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns HTML into searchable text. Pure: no database or HTTP.
 */
final class PageText
{
    public const MAX_BYTES = 204_800;

    /**
     * Visible text only (no scripts, styles, or comments), entities decoded, lowercased,
     * single spaces, capped at 200 KB.
     */
    public static function from(string $html): string
    {
        $html = mb_scrub($html, 'UTF-8');
        $html = (string) preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>|<!--.*?-->#is', ' ', $html);
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));

        return mb_strcut($text, 0, self::MAX_BYTES, 'UTF-8');
    }
}
