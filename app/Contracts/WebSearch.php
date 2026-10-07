<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\WebSearchRequestFailed;
use App\Services\SearchResult;

/**
 * Finds candidate websites. Behind an interface so the search provider can change.
 */
interface WebSearch
{
    /**
     * Search the web. An empty or malformed response gives an empty list.
     *
     * @return list<SearchResult>
     *
     * @throws WebSearchRequestFailed
     */
    public function search(string $query): array;
}
