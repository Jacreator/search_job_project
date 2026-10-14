<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WebSearch;
use App\Enums\AiSource;
use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Services\CompaniesHouse;
use App\Services\SharedLookup;
use App\Services\WebsiteScorer;
use App\Services\WebsiteVerifier;
use App\Support\AiDetector;
use App\Support\EmployerClassifier;
use App\Support\SearchQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Runs the lookup pipeline for one sponsor. Safe to run twice: each step checks the status first.
 * Unique per sponsor, so a row still waiting on a retry is not queued again by the next batch.
 */
final class EnrichSponsor implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Fail after this many errors. Waiting on the rate limiter releases the job without
     * counting, so `$tries` is not used (each release would use up a try).
     */
    public int $maxExceptions = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public bool $deleteWhenMissingModels = true;

    /**
     * Highest confidence for a copy of another team's confirmed website: just under the trusted 75.
     */
    public const SHARED_CONFIRMED_CONFIDENCE = 74;

    /**
     * Seconds the unique lock is held at most, the same as `retryUntil()`. The lock is released
     * as soon as the job finishes or fails.
     */
    public int $uniqueFor = 86400;

    public function __construct(
        public readonly Sponsor $sponsor,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('external-apis')];
    }

    public function retryUntil(): CarbonInterface
    {
        return now()->addSeconds($this->uniqueFor);
    }

    public function uniqueId(): string
    {
        return (string) $this->sponsor->getKey();
    }

    public function handle(
        CompaniesHouse $companiesHouse,
        EmployerClassifier $classifier,
        WebSearch $search,
        WebsiteScorer $scorer,
        WebsiteVerifier $verifier,
        SharedLookup $sharedLookup,
        AiDetector $aiDetector,
    ): void {
        $sponsor = $this->sponsor;

        if (! in_array($sponsor->status, [SponsorStatus::Pending, SponsorStatus::ChDone], true)) {
            return;
        }

        $sponsor->increment('attempts');

        $source = $sharedLookup->find($sponsor);

        if ($source !== null) {
            $this->copyFrom($source);

            return;
        }

        if ($sponsor->status === SponsorStatus::Pending) {
            $match = $companiesHouse->findCompany($sponsor->name);

            if ($match === null) {
                $this->skip(SkipReason::NoMatch);

                return;
            }

            $profile = $companiesHouse->profile($match->number);
            $techReason = $classifier->classify($sponsor->name, $profile->sicCodes);
            $aiFromName = $aiDetector->fromName($sponsor->name);

            $sponsor->update([
                'company_number' => $profile->number,
                'company_status' => $profile->status,
                'sic_codes' => $profile->sicCodes,
                'tech_reason' => $techReason,
                'is_tech' => $techReason !== null,
                ...($aiFromName ? ['is_ai' => true, 'ai_source' => AiSource::Name] : []),
                'status' => SponsorStatus::ChDone,
            ]);
        }

        if ($sponsor->company_status !== 'active') {
            $this->skip(SkipReason::Inactive);

            return;
        }

        if ($sponsor->is_tech !== true) {
            $this->skip(SkipReason::NotTech);

            return;
        }

        // Search credits are only spent here, on active tech sponsors.
        $candidate = $scorer->best($sponsor->name, $search->search(SearchQuery::for($sponsor->name, $sponsor->town)));

        $page = $candidate === null ? null : $verifier->verify($candidate->url, $sponsor->name);

        // The homepage text is already fetched, so the AI check costs no extra request.
        $aiFromHomepage = $page !== null && ! $sponsor->is_ai && $aiDetector->fromText($page->text);

        $sponsor->update([
            'website' => $candidate?->url,
            'confidence' => $candidate === null || $page === null ? null : $scorer->confidence($candidate, $page),
            ...($aiFromHomepage ? ['is_ai' => true, 'ai_source' => AiSource::Homepage] : []),
            'status' => SponsorStatus::Done,
            'skip_reason' => null,
            'error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Sponsor::query()->whereKey($this->sponsor->getKey())->update([
            'status' => SponsorStatus::Failed,
            'error' => mb_substr($exception?->getMessage() ?: 'Unknown error.', 0, 500),
        ]);
    }

    /**
     * Copy another team's finished lookup instead of calling the APIs. A hand-confirmed website
     * is capped just under the trusted threshold and flagged until this team checks it.
     * Confirmed, region, priority, and rating stay this team's own.
     */
    private function copyFrom(Sponsor $source): void
    {
        $confidence = $source->confirmed && $source->confidence !== null
            ? min($source->confidence, self::SHARED_CONFIRMED_CONFIDENCE)
            : $source->confidence;

        $this->sponsor->update([
            'company_number' => $source->company_number,
            'company_status' => $source->company_status,
            'sic_codes' => $source->sic_codes,
            'is_tech' => $source->is_tech,
            'tech_reason' => $source->tech_reason,
            'is_ai' => $source->is_ai,
            'ai_source' => $source->ai_source,
            'skip_reason' => $source->skip_reason,
            'website' => $source->website,
            'confidence' => $confidence,
            'needs_second_check' => $source->confirmed,
            'status' => $source->status,
            'error' => null,
        ]);
    }

    private function skip(SkipReason $reason): void
    {
        $this->sponsor->update([
            'status' => SponsorStatus::Skipped,
            'skip_reason' => $reason,
            'error' => null,
        ]);
    }
}
