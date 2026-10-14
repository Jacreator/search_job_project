<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Support\CompanyName;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds a finished lookup for the same company in another team, so the enrich job can copy it
 * instead of calling the APIs. Both teams must have `share_lookups` on. Database only.
 */
final class SharedLookup
{
    /**
     * The best source row, or null when the sponsor's team does not share or no team has a match.
     * Confirmed rows first, then the highest confidence, then the most recently updated.
     */
    public function find(Sponsor $sponsor): ?Sponsor
    {
        if ($sponsor->team->share_lookups !== true) {
            return null;
        }

        $name = CompanyName::normalise($sponsor->name);

        if ($name === '') {
            return null;
        }

        return Sponsor::query()
            ->where('normalised_name', $name)
            ->where('team_id', '!=', $sponsor->team_id)
            ->whereHas('team', fn (Builder $team) => $team->where('share_lookups', true))
            ->whereIn('status', [SponsorStatus::Done, SponsorStatus::Skipped])
            ->where(fn (Builder $query) => $query
                ->whereNull('skip_reason')
                ->orWhere('skip_reason', '!=', SkipReason::BRating))
            ->whereRaw('lower(town) = ?', [mb_strtolower((string) $sponsor->town)])
            ->orderByDesc('confirmed')
            ->orderByDesc('confidence')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            // The MySQL collation also ignores accents, so the exact match is checked here.
            ->first(fn (Sponsor $source): bool => $source->normalised_name === $name);
    }
}
