<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\WebSearch;
use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Services\CompaniesHouse;
use App\Services\WebsiteScorer;
use App\Services\WebsiteVerifier;
use App\Support\EmployerClassifier;
use App\Support\SearchQuery;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Runs the lookup pipeline for one sponsor. Safe to run twice: each step checks the status first.
 */
final class EnrichSponsor implements ShouldQueue
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
        return now()->addDay();
    }

    public function handle(
        CompaniesHouse $companiesHouse,
        EmployerClassifier $classifier,
        WebSearch $search,
        WebsiteScorer $scorer,
        WebsiteVerifier $verifier,
    ): void {
        $sponsor = $this->sponsor;

        if (! in_array($sponsor->status, [SponsorStatus::Pending, SponsorStatus::ChDone], true)) {
            return;
        }

        $sponsor->increment('attempts');

        if ($sponsor->status === SponsorStatus::Pending) {
            $match = $companiesHouse->findCompany($sponsor->name);

            if ($match === null) {
                $this->skip(SkipReason::NoMatch);

                return;
            }

            $profile = $companiesHouse->profile($match->number);
            $techReason = $classifier->classify($sponsor->name, $profile->sicCodes);

            $sponsor->update([
                'company_number' => $profile->number,
                'company_status' => $profile->status,
                'sic_codes' => $profile->sicCodes,
                'tech_reason' => $techReason,
                'is_tech' => $techReason !== null,
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

        $sponsor->update([
            'website' => $candidate?->url,
            'confidence' => $candidate === null
                ? null
                : $scorer->confidence($candidate, $verifier->verify($candidate->url, $sponsor->name)),
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

    private function skip(SkipReason $reason): void
    {
        $this->sponsor->update([
            'status' => SponsorStatus::Skipped,
            'skip_reason' => $reason,
            'error' => null,
        ]);
    }
}
