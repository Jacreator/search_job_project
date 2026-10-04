<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use Illuminate\Container\Attributes\Config;

final class RatingChange
{
    public function __construct(
        #[Config('sponsor-finder.skip_b_rated')] private readonly bool $skipBRated,
    ) {}

    /**
     * Set a sponsor's grade and apply the status rules for the change. Does not save.
     *
     * @param  'A'|'B'  $grade
     */
    public function apply(Sponsor $sponsor, string $grade): void
    {
        $previous = $sponsor->rating_grade;
        $sponsor->rating_grade = $grade;

        if ($previous === $grade || ! $this->skipBRated) {
            return;
        }

        $skippedForBRating = $sponsor->status === SponsorStatus::Skipped
            && $sponsor->skip_reason === SkipReason::BRating;

        if ($grade === 'B' && ($sponsor->status !== SponsorStatus::Skipped || $skippedForBRating)) {
            $sponsor->status = SponsorStatus::Skipped;
            $sponsor->skip_reason = SkipReason::BRating;
        }

        if ($grade === 'A' && $skippedForBRating) {
            $sponsor->status = SponsorStatus::Pending;
            $sponsor->skip_reason = null;
        }
    }
}
