<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SponsorStatus;
use App\Exceptions\ExportFailed;
use App\Models\Sponsor;
use App\Models\Team;
use App\Support\CsvCell;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Writes one team's finished sponsors with a website to a CSV file, highest confidence first.
 */
final class SponsorExport
{
    public const HEADERS = [
        'Name',
        'Town',
        'Region',
        'Company number',
        'SIC codes',
        'Website',
        'Confidence',
        'Confirmed',
        'Scale-up',
        'AI',
        'Needs second check',
    ];

    /**
     * Tells Excel the file is UTF-8.
     */
    private const BOM = "\xEF\xBB\xBF";

    /**
     * Write the export to the path and return the number of sponsors written.
     * The file is written under a temporary name first, so a failed export never leaves a partial file.
     *
     * @throws ExportFailed
     */
    public function export(Team $team, ExportFilters $filters, string $path): int
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ExportFailed::cannotWrite($path);
        }

        $part = $path.'.part';
        $file = @fopen($part, 'w');

        if ($file === false) {
            throw ExportFailed::cannotWrite($path);
        }

        $count = 0;

        try {
            fwrite($file, self::BOM);
            fputcsv($file, self::HEADERS, ',', '"', '');

            foreach ($this->query($team, $filters)->lazy() as $sponsor) {
                fputcsv($file, $this->row($sponsor), ',', '"', '');
                $count++;
            }
        } catch (Throwable $exception) {
            fclose($file);
            @unlink($part);

            throw $exception;
        }

        fclose($file);

        if (! @rename($part, $path)) {
            @unlink($part);

            throw ExportFailed::cannotWrite($path);
        }

        return $count;
    }

    /**
     * @return Builder<Sponsor>
     */
    private function query(Team $team, ExportFilters $filters): Builder
    {
        $query = Sponsor::query()
            ->where('team_id', $team->id)
            ->where('status', SponsorStatus::Done)
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->where('confidence', '>=', $filters->minConfidence);

        // Case-insensitive on every database, like the MySQL collation.
        foreach (['town' => $filters->town, 'region' => $filters->region] as $column => $value) {
            if ($value !== null && $value !== '') {
                $query->whereRaw("lower({$column}) = ?", [mb_strtolower($value)]);
            }
        }

        if ($filters->confirmedOnly) {
            $query->where('confirmed', true);
        }

        if ($filters->scaleUpOnly) {
            $query->scaleUp();
        }

        return $query->orderByDesc('confidence')->orderBy('name')->orderBy('id');
    }

    /**
     * @return list<string|int>
     */
    private function row(Sponsor $sponsor): array
    {
        return [
            CsvCell::safe($sponsor->name),
            CsvCell::safe($sponsor->town),
            CsvCell::safe($sponsor->region),
            CsvCell::safe($sponsor->company_number),
            CsvCell::safe(implode(', ', $sponsor->sic_codes ?? [])),
            CsvCell::safe($sponsor->website),
            (int) $sponsor->confidence,
            $sponsor->confirmed ? 'Yes' : 'No',
            str_contains($sponsor->route, Sponsor::SCALE_UP_ROUTE) ? 'Yes' : 'No',
            $sponsor->is_ai ? 'Yes' : 'No',
            $sponsor->needs_second_check ? 'Yes' : 'No',
        ];
    }
}
