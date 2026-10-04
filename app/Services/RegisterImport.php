<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Exceptions\InvalidRegisterFile;
use App\Models\Sponsor;
use App\Models\Team;
use App\Support\PriorityLocations;
use App\Support\SponsorRating;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Imports the sponsor register CSV into one team, row by row.
 *
 * The register has one row per organization per route, so rows with the same
 * name and town are merged into one sponsor: routes are joined, the first
 * county is kept, and a B grade beats an A grade.
 *
 * @phpstan-type Entry array{key: string, name: string, town: string, county: string|null, rating: string, grade: 'A'|'B'|null, routes: list<string>}
 */
final class RegisterImport
{
    public const REQUIRED_HEADERS = ['Organisation Name', 'Town/City', 'County', 'Type & Rating', 'Route'];

    private const CHUNK_SIZE = 500;

    private const MAX_LENGTH = 255;

    private const ROUTE_SEPARATOR = ', ';

    /**
     * Keys written during this run, mapped to whether the sponsor existed before the run.
     *
     * @var array<string, bool>
     */
    private array $written = [];

    private int $read = 0;

    private int $ignored = 0;

    private int $new = 0;

    private int $existing = 0;

    private int $movedToSkipped = 0;

    private int $movedToPending = 0;

    /**
     * @param  list<string>  $routes
     */
    public function __construct(
        private readonly RatingChange $ratingChange,
        private readonly PriorityLocations $priorityLocations,
        #[Config('sponsor-finder.routes')] private readonly array $routes,
        #[Config('sponsor-finder.skip_b_rated')] private readonly bool $skipBRated,
    ) {}

    /**
     * Import the register file into the team. The callback runs once per data row.
     *
     * @throws InvalidRegisterFile
     */
    public function import(Team $team, string $path, ?Closure $onRow = null): ImportSummary
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw InvalidRegisterFile::missing($path);
        }

        $this->reset();

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');

        $columns = null;

        /** @var array<string, Entry> $buffer */
        $buffer = [];

        foreach ($file as $cells) {
            if (! is_array($cells) || $cells === [null]) {
                continue;
            }

            if ($columns === null) {
                $columns = $this->columns($cells);

                continue;
            }

            $this->read++;

            if ($onRow !== null) {
                $onRow();
            }

            $entry = $this->entry($cells, $columns);

            if ($entry === null) {
                $this->ignored++;

                continue;
            }

            $key = $entry['key'];
            $buffer[$key] = isset($buffer[$key]) ? $this->merge($buffer[$key], $entry) : $entry;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->flush($team, $buffer);
                $buffer = [];
            }
        }

        if ($columns === null) {
            throw InvalidRegisterFile::missingHeaders(self::REQUIRED_HEADERS);
        }

        $this->flush($team, $buffer);

        return new ImportSummary(
            $this->read,
            $this->ignored,
            $this->new,
            $this->existing,
            $this->movedToSkipped,
            $this->movedToPending,
        );
    }

    /**
     * Map each required header to its column index.
     *
     * @param  array<int, string|null>  $cells
     * @return array<string, int>
     *
     * @throws InvalidRegisterFile
     */
    private function columns(array $cells): array
    {
        $headers = array_map(fn (?string $header): string => trim((string) $header), $cells);
        $headers[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '');

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));

        if ($missing !== []) {
            throw InvalidRegisterFile::missingHeaders($missing);
        }

        return array_intersect_key(array_flip($headers), array_flip(self::REQUIRED_HEADERS));
    }

    /**
     * Build an entry from a CSV row, or null when the row is not imported.
     *
     * @param  array<int, string|null>  $cells
     * @param  array<string, int>  $columns
     * @return Entry|null
     */
    private function entry(array $cells, array $columns): ?array
    {
        $route = $this->cell($cells, $columns['Route']);
        $name = $this->cell($cells, $columns['Organisation Name']);

        if ($name === '' || ! in_array($route, $this->routes, true)) {
            return null;
        }

        $town = $this->cell($cells, $columns['Town/City']);
        $county = $this->cell($cells, $columns['County']);
        $rating = $this->cell($cells, $columns['Type & Rating']);

        return [
            'key' => self::key($name, $town),
            'name' => $name,
            'town' => $town,
            'county' => $county === '' ? null : $county,
            'rating' => $rating,
            'grade' => SponsorRating::grade($rating),
            'routes' => [$route],
        ];
    }

    /**
     * Read a cell as untrusted text: trimmed and capped to the column size.
     *
     * @param  array<int, string|null>  $cells
     */
    private function cell(array $cells, int $index): string
    {
        return mb_substr(trim((string) ($cells[$index] ?? '')), 0, self::MAX_LENGTH);
    }

    /**
     * Merge another register row for the same sponsor into an entry.
     *
     * @param  Entry  $into
     * @param  Entry  $row
     * @return Entry
     */
    private function merge(array $into, array $row): array
    {
        $into['routes'] = array_values(array_unique([...$into['routes'], ...$row['routes']]));
        $into['county'] ??= $row['county'];

        if (self::worstGrade($into['grade'], $row['grade']) !== $into['grade']) {
            $into['grade'] = $row['grade'];
            $into['rating'] = $row['rating'];
        }

        return $into;
    }

    /**
     * Write a chunk of entries: insert new sponsors and update existing ones.
     *
     * @param  array<string, Entry>  $buffer
     */
    private function flush(Team $team, array $buffer): void
    {
        if ($buffer === []) {
            return;
        }

        DB::transaction(function () use ($team, $buffer): void {
            $existing = $team->sponsors()
                ->whereIn('name', array_unique(array_column($buffer, 'name')))
                ->get()
                ->keyBy(fn (Sponsor $sponsor): string => self::key($sponsor->name, (string) $sponsor->town));

            $rows = [];

            foreach ($buffer as $key => $entry) {
                $sponsor = $existing->get($key);

                if ($sponsor === null) {
                    $rows[] = $this->newRow($team, $entry);
                    $this->written[$key] = false;
                    $this->new++;

                    continue;
                }

                $this->update($sponsor, $entry, $this->written[$key] ?? null);

                if (! isset($this->written[$key])) {
                    $this->written[$key] = true;
                    $this->existing++;
                }
            }

            if ($rows !== []) {
                Sponsor::query()->insert($rows);
            }
        });
    }

    /**
     * @param  Entry  $entry
     * @return array<string, mixed>
     */
    private function newRow(Team $team, array $entry): array
    {
        $location = $this->priorityLocations->for($entry['town']);
        $skipped = $this->skipBRated && $entry['grade'] === 'B';
        $now = now();

        return [
            'team_id' => $team->id,
            'name' => $entry['name'],
            'town' => $entry['town'],
            'county' => $entry['county'],
            'route' => implode(self::ROUTE_SEPARATOR, $entry['routes']),
            'rating' => $entry['rating'],
            'rating_grade' => $entry['grade'],
            'rating_grade_manual' => false,
            'region' => $location->region,
            'priority' => $location->priority,
            'status' => ($skipped ? SponsorStatus::Skipped : SponsorStatus::Pending)->value,
            'skip_reason' => $skipped ? SkipReason::BRating->value : null,
            'attempts' => 0,
            'confirmed' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Update an existing sponsor's register fields. Lookup results are never touched.
     *
     * $writtenThisRun is null the first time the sponsor is seen in this run (a re-import),
     * otherwise this run already wrote it and the entry is merged in: true when the sponsor
     * existed before the run, false when this run created it.
     *
     * @param  Entry  $entry
     */
    private function update(Sponsor $sponsor, array $entry, ?bool $writtenThisRun): void
    {
        $merging = $writtenThisRun !== null;
        $wasSkippedForBRating = self::skippedForBRating($sponsor);
        $location = $this->priorityLocations->for($entry['town']);

        $routes = $merging
            ? array_values(array_unique([...explode(self::ROUTE_SEPARATOR, $sponsor->route), ...$entry['routes']]))
            : $entry['routes'];

        $sponsor->route = implode(self::ROUTE_SEPARATOR, $routes);
        $sponsor->county = $merging ? ($sponsor->county ?? $entry['county']) : $entry['county'];
        $sponsor->region = $location->region;
        $sponsor->priority = $location->priority;

        $grade = $entry['grade'];
        $takeGrade = $grade !== null
            && (! $merging || self::worstGrade($sponsor->rating_grade, $grade) !== $sponsor->rating_grade);

        if ($takeGrade) {
            $this->ratingChange->apply($sponsor, $grade);
            $sponsor->rating_grade_manual = false;
        }

        if ($takeGrade || ! $merging) {
            $sponsor->rating = $entry['rating'];
        }

        if ($writtenThisRun !== false) {
            $isSkippedForBRating = self::skippedForBRating($sponsor);

            if (! $wasSkippedForBRating && $isSkippedForBRating) {
                $this->movedToSkipped++;
            }

            if ($wasSkippedForBRating && $sponsor->status === SponsorStatus::Pending) {
                $this->movedToPending++;
            }
        }

        if ($sponsor->isDirty()) {
            $sponsor->save();
        }
    }

    private function reset(): void
    {
        $this->written = [];
        $this->read = 0;
        $this->ignored = 0;
        $this->new = 0;
        $this->existing = 0;
        $this->movedToSkipped = 0;
        $this->movedToPending = 0;
    }

    /**
     * Match key for name and town. Folds case and accents like the MySQL collation does,
     * so two spellings the unique index treats as equal become one sponsor.
     */
    private static function key(string $name, string $town): string
    {
        return Str::lower(Str::ascii($name))."\0".Str::lower(Str::ascii($town));
    }

    /**
     * @param  'A'|'B'|null  $current
     * @param  'A'|'B'|null  $other
     * @return 'A'|'B'|null
     */
    private static function worstGrade(?string $current, ?string $other): ?string
    {
        if ($current === 'B' || $other === 'B') {
            return 'B';
        }

        return $current ?? $other;
    }

    private static function skippedForBRating(Sponsor $sponsor): bool
    {
        return $sponsor->status === SponsorStatus::Skipped
            && $sponsor->skip_reason === SkipReason::BRating;
    }
}
