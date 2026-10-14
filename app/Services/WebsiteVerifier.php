<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CompanyName;
use App\Support\PageText;

/**
 * Loads a homepage, checks it for the company name, and returns the page text.
 */
final class WebsiteVerifier
{
    public function __construct(
        private readonly PageFetcher $fetcher,
    ) {}

    public function verify(string $url, string $name): VerifiedPage
    {
        $page = $this->fetcher->get($url);

        if ($page === null || $page->status < 200 || $page->status >= 300) {
            return VerifiedPage::failed();
        }

        $text = PageText::from($page->body);
        $firstWord = explode(' ', CompanyName::normalise($name))[0];

        return new VerifiedPage($firstWord !== '' && self::containsWord($text, $firstWord), $text);
    }

    /**
     * Whole-word match. Apostrophes are dropped first, as `CompanyName` does ("sainsbury's" holds "sainsburys").
     */
    private static function containsWord(string $text, string $word): bool
    {
        $text = str_replace(["'", "\u{2019}"], '', $text);

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $text) === 1;
    }
}
