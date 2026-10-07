<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CompanyName;
use Illuminate\Container\Attributes\Config;

/**
 * Picks the sponsor's website from search results. Pure, no HTTP.
 */
final class WebsiteScorer
{
    private const FULL_NAME_IN_HOST = 60;

    private const FIRST_WORD_IN_HOST = 40;

    private const FIRST_WORD_IN_TITLE = 15;

    private const NAME_ON_PAGE = 25;

    private const MAX_SCORE = 100;

    // Shorter first words ("it", "a1") must start a host label, or they would match almost any host.
    private const MIN_FIRST_WORD_LENGTH = 3;

    /**
     * @param  list<string>  $blockedDomains
     */
    public function __construct(
        #[Config('sponsor-finder.blocked_domains')] private readonly array $blockedDomains,
    ) {}

    /**
     * The homepage of the highest scoring result, or null when no unblocked result scores.
     * Ties go to the earlier search result.
     *
     * @param  list<SearchResult>  $results
     */
    public function best(string $name, array $results): ?WebsiteCandidate
    {
        $words = explode(' ', CompanyName::normalise($name));

        if ($words === ['']) {
            return null;
        }

        $best = null;

        foreach ($results as $result) {
            $parts = parse_url($result->url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));

            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $this->isBlocked($host)) {
                continue;
            }

            $score = $this->score($words, $host, $result->title);

            if ($score > 0 && ($best === null || $score > $best->score)) {
                $best = new WebsiteCandidate("{$scheme}://{$host}", $score);
            }
        }

        return $best;
    }

    /**
     * The final confidence once the homepage has been checked for the name.
     */
    public function confidence(WebsiteCandidate $candidate, VerifiedPage $page): int
    {
        return min(self::MAX_SCORE, $candidate->score + ($page->mentionsName ? self::NAME_ON_PAGE : 0));
    }

    /**
     * @param  non-empty-list<string>  $words
     */
    private function score(array $words, string $host, string $title): int
    {
        // Hyphens are ignored, so "sumo-digital.com" holds "sumodigital".
        $hostText = str_replace('-', '', $host);
        $firstWord = $words[0];
        $score = 0;

        if (str_contains($hostText, implode('', $words))) {
            $score += self::FULL_NAME_IN_HOST;
        } elseif (self::firstWordInHost($firstWord, $hostText)) {
            $score += self::FIRST_WORD_IN_HOST;
        }

        if (in_array($firstWord, explode(' ', CompanyName::normalise($title)), true)) {
            $score += self::FIRST_WORD_IN_TITLE;
        }

        return min(self::MAX_SCORE, $score);
    }

    private static function firstWordInHost(string $firstWord, string $hostText): bool
    {
        if (mb_strlen($firstWord) >= self::MIN_FIRST_WORD_LENGTH) {
            return str_contains($hostText, $firstWord);
        }

        foreach (explode('.', $hostText) as $label) {
            if (str_starts_with($label, $firstWord)) {
                return true;
            }
        }

        return false;
    }

    private function isBlocked(string $host): bool
    {
        foreach ($this->blockedDomains as $domain) {
            $domain = strtolower($domain);

            $blocked = str_contains($domain, '.')
                ? $host === $domain || str_ends_with($host, ".{$domain}")
                : str_contains($host, $domain);

            if ($blocked) {
                return true;
            }
        }

        return false;
    }
}
