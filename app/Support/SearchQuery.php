<?php

declare(strict_types=1);

namespace App\Support;

final class SearchQuery
{
    /**
     * Build the web search query from the original sponsor name and town: "{name} {town}".
     */
    public static function for(string $name, ?string $town): string
    {
        return trim(trim($name).' '.trim((string) $town));
    }
}
