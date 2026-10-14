<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads one team's sponsors for the review dashboard. Database only.
 *
 * @phpstan-type Row array{id: int, name: string, town: string|null, region: string|null, rating_grade: string|null, rating_grade_manual: bool, sic_codes: array<int, string>, website: string|null, confidence: int|null, confirmed: bool, needs_second_check: bool, status: string, skip_reason: string|null, is_scale_up: bool}
 */
final class SponsorDashboard
{
    public const PER_PAGE = 50;

    /**
     * One page of the team's sponsors: rows with a website first (highest confidence first),
     * then by priority, then by name.
     *
     * @return LengthAwarePaginator<int, Row>
     */
    public function sponsors(Team $team, SponsorFilters $filters): LengthAwarePaginator
    {
        $query = $team->sponsors()->getQuery();

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // Case-insensitive on every database, like the MySQL collation.
        foreach (['town' => $filters->town, 'region' => $filters->region] as $column => $value) {
            if ($value !== null) {
                $query->whereRaw("lower({$column}) = ?", [mb_strtolower($value)]);
            }
        }

        if ($filters->minConfidence !== null) {
            $query->where('confidence', '>=', $filters->minConfidence);
        }

        if ($filters->tech) {
            $query->tech();
        }

        if ($filters->scaleUp) {
            $query->scaleUp();
        }

        if ($filters->ratingMissing) {
            $query->whereNull('rating_grade');
        }

        if ($filters->needsSecondCheck) {
            $query->where('needs_second_check', true);
        }

        if ($filters->search !== null) {
            $query->whereRaw("lower(name) like ? escape '!'", [
                '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters->search)).'%',
            ]);
        }

        return $query
            ->orderByDesc('confidence')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Sponsor $sponsor): array => [
                'id' => $sponsor->id,
                'name' => $sponsor->name,
                'town' => $sponsor->town,
                'region' => $sponsor->region,
                'rating_grade' => $sponsor->rating_grade,
                'rating_grade_manual' => $sponsor->rating_grade_manual,
                'sic_codes' => $sponsor->sic_codes ?? [],
                'website' => $sponsor->website,
                'confidence' => $sponsor->confidence,
                'confirmed' => $sponsor->confirmed,
                'needs_second_check' => $sponsor->needs_second_check,
                'status' => $sponsor->status->value,
                'skip_reason' => $sponsor->skip_reason?->value,
                'is_scale_up' => str_contains($sponsor->route, Sponsor::SCALE_UP_ROUTE),
            ]);
    }

    /**
     * The team's sponsor count per status, every status listed.
     *
     * @return list<array{value: string, count: int}>
     */
    public function statusCounts(Team $team): array
    {
        $counts = $this->countsBy($team, 'status');

        return array_map(
            fn (SponsorStatus $status): array => ['value' => $status->value, 'count' => $counts[$status->value] ?? 0],
            SponsorStatus::cases(),
        );
    }

    /**
     * The team's skipped sponsor count per skip reason, every reason listed.
     *
     * @return list<array{value: string, count: int}>
     */
    public function skipReasonCounts(Team $team): array
    {
        $counts = $this->countsBy($team, 'skip_reason', SponsorStatus::Skipped);

        return array_map(
            fn (SkipReason $reason): array => ['value' => $reason->value, 'count' => $counts[$reason->value] ?? 0],
            SkipReason::cases(),
        );
    }

    /**
     * The regions the team has sponsors in, for the region filter.
     *
     * @return list<string>
     */
    public function regions(Team $team): array
    {
        return array_values($team->sponsors()
            ->toBase()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->map(fn (mixed $region): string => (string) $region)
            ->all());
    }

    /**
     * @return array<string, int>
     */
    private function countsBy(Team $team, string $column, ?SponsorStatus $status = null): array
    {
        $query = $team->sponsors()->toBase()->whereNotNull($column);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $counts = [];

        foreach ($query->select($column)->selectRaw('count(*) as aggregate')->groupBy($column)->get() as $row) {
            $counts[(string) $row->{$column}] = (int) $row->aggregate;
        }

        return $counts;
    }
}
