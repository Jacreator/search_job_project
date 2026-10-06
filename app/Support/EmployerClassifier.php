<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TechReason;
use Illuminate\Container\Attributes\Config;

/**
 * Decides whether a sponsor is a tech employer, and why. Pure: no database or HTTP.
 */
final class EmployerClassifier
{
    /**
     * @var array<string, true>
     */
    private array $techSicCodes;

    /**
     * @var array<string, true>
     */
    private array $techKeywords;

    /**
     * @var array<string, true>
     */
    private array $noiseKeywords;

    /**
     * @var array<string, true>
     */
    private array $knownEmployers;

    /**
     * @param  list<string>  $techSicCodes
     * @param  list<string>  $techKeywords
     * @param  list<string>  $noiseKeywords
     * @param  list<string>  $knownEmployers
     */
    public function __construct(
        #[Config('sponsor-finder.tech_sic_codes')] array $techSicCodes,
        #[Config('sponsor-finder.tech_keywords')] array $techKeywords,
        #[Config('sponsor-finder.noise_keywords')] array $noiseKeywords,
        #[Config('sponsor-finder.known_employers')] array $knownEmployers,
    ) {
        $this->techSicCodes = array_fill_keys(array_map('trim', $techSicCodes), true);
        $this->techKeywords = array_fill_keys(array_map('mb_strtolower', $techKeywords), true);
        $this->noiseKeywords = array_fill_keys(array_map('mb_strtolower', $noiseKeywords), true);
        $this->knownEmployers = array_fill_keys(array_map(self::registerName(...), $knownEmployers), true);
    }

    /**
     * Classify a sponsor. Checks run in order and stop at the first hit:
     * a tech SIC code, then a tech keyword with no noise keyword, then a known employer.
     *
     * @param  array<int, string>  $sicCodes
     */
    public function classify(string $name, array $sicCodes): ?TechReason
    {
        foreach ($sicCodes as $code) {
            if (isset($this->techSicCodes[trim($code)])) {
                return TechReason::Sic;
            }
        }

        $words = array_fill_keys(explode(' ', CompanyName::normalise($name)), true);

        if (array_intersect_key($words, $this->techKeywords) !== []
            && array_intersect_key($words, $this->noiseKeywords) === []) {
            return TechReason::Keyword;
        }

        if (isset($this->knownEmployers[self::registerName($name)])) {
            return TechReason::KnownEmployer;
        }

        return null;
    }

    /**
     * Compare register names ignoring only letter case and extra spaces.
     */
    private static function registerName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }
}
