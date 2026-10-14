<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Sponsor;
use App\Services\CareersPageFinder;
use App\Support\CareersSignals;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Finds one sponsor's careers page and reads its hiring signals. Never changes `status`.
 * Safe to run twice: a row with `careers_checked_at` set is left alone.
 */
final class FindCareersPage implements ShouldBeUnique, ShouldQueue
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
     * Seconds the unique lock is held at most, the same as `retryUntil()`.
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
        return [new RateLimited('careers-pages')];
    }

    public function retryUntil(): CarbonInterface
    {
        return now()->addSeconds($this->uniqueFor);
    }

    public function uniqueId(): string
    {
        return (string) $this->sponsor->getKey();
    }

    public function handle(CareersPageFinder $finder, CareersSignals $signals): void
    {
        $sponsor = $this->sponsor;

        if ($sponsor->careers_checked_at !== null || ! $sponsor->eligibleForCareersCheck()) {
            return;
        }

        $page = $finder->find((string) $sponsor->website);

        $sponsor->update([
            'careers_url' => $page?->url,
            'mentions_developer_roles' => $page === null ? null : $signals->mentionsDeveloperRoles($page->text),
            'visa_sponsorship' => $page === null ? null : $signals->visaSponsorship($page->text),
            'careers_checked_at' => now(),
        ]);
    }

    /**
     * Mark the row checked with no result, so the next batch does not queue it again.
     */
    public function failed(): void
    {
        Sponsor::query()->whereKey($this->sponsor->getKey())->update([
            'careers_url' => null,
            'mentions_developer_roles' => null,
            'visa_sponsorship' => null,
            'careers_checked_at' => now(),
        ]);
    }
}
