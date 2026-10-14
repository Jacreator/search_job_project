<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Container\Attributes\Config;

/**
 * Tags AI companies from their name or homepage text. Pure: no database or HTTP.
 */
final readonly class AiDetector
{
    /**
     * @param  list<string>  $aiKeywords
     */
    public function __construct(
        #[Config('sponsor-finder.ai_keywords')] private array $aiKeywords,
    ) {}

    /**
     * Whether the normalised company name contains an AI term.
     */
    public function fromName(string $name): bool
    {
        return TermMatcher::contains(CompanyName::normalise($name), $this->aiKeywords);
    }

    /**
     * Whether the page text contains an AI term.
     */
    public function fromText(string $text): bool
    {
        return TermMatcher::contains($text, $this->aiKeywords);
    }
}
