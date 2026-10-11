<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ExportFailed;
use App\Models\Team;
use App\Services\ExportFilters;
use App\Services\SponsorExport;
use Illuminate\Console\Command;

class ExportSponsors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sponsors:export
        {--team= : Slug of the team to export}
        {--min-confidence=50 : Only sponsors with at least this website confidence (0 to 100)}
        {--town= : Only sponsors in this town}
        {--region= : Only sponsors in this region}
        {--confirmed : Only sponsors with a confirmed website}
        {--scale-up : Only Scale-up visa sponsors}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export a team\'s finished sponsors with a website to a CSV file';

    /**
     * Execute the console command.
     */
    public function handle(SponsorExport $export): int
    {
        $slug = trim((string) $this->option('team'));

        if ($slug === '') {
            $this->error('The --team option is required.');

            return self::FAILURE;
        }

        $team = Team::query()->where('slug', $slug)->first();

        if ($team === null) {
            $this->error("Team [{$slug}] does not exist.");

            return self::FAILURE;
        }

        $minConfidence = filter_var(
            $this->option('min-confidence'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 100]],
        );

        if ($minConfidence === false) {
            $this->error('The --min-confidence option must be a whole number from 0 to 100.');

            return self::FAILURE;
        }

        $filters = new ExportFilters(
            minConfidence: $minConfidence,
            town: trim((string) $this->option('town')),
            region: trim((string) $this->option('region')),
            confirmedOnly: (bool) $this->option('confirmed'),
            scaleUpOnly: (bool) $this->option('scale-up'),
        );

        $path = storage_path("app/exports/{$team->slug}_tech_sponsors_".now()->toDateString().'.csv');

        try {
            $count = $export->export($team, $filters, $path);
        } catch (ExportFailed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Exported {$count} ".str('sponsor')->plural($count)." to {$path}");

        return self::SUCCESS;
    }
}
