<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnrichSponsor;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\UniqueJobSkipped;
use Illuminate\Support\Facades\Event;

class EnrichSponsors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sponsors:enrich
        {--limit= : How many sponsors to queue this run, across all teams (default from config)}
        {--town= : Only sponsors in this town}
        {--region= : Only sponsors in this region}
        {--team= : Only sponsors of the team with this slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue a lookup job for the next batch of sponsors, highest priority first';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->limit();

        if ($limit === null) {
            $this->error('The --limit option must be a whole number above 0.');

            return self::FAILURE;
        }

        $query = Sponsor::query()->readyForLookup();

        $slug = trim((string) $this->option('team'));

        if ($slug !== '') {
            $team = Team::query()->where('slug', $slug)->first();

            if ($team === null) {
                $this->error("Team [{$slug}] does not exist.");

                return self::FAILURE;
            }

            $query->where('team_id', $team->id);
        }

        // Case-insensitive on every database, like the MySQL collation.
        foreach (['town', 'region'] as $column) {
            $value = trim((string) $this->option($column));

            if ($value !== '') {
                $query->whereRaw("lower({$column}) = ?", [mb_strtolower($value)]);
            }
        }

        $sponsors = $query->orderByDesc('priority')->orderBy('id')->limit($limit)->get();

        // A sponsor with a lookup still queued (for example waiting on a retry) is not queued again.
        $alreadyQueued = 0;

        Event::listen(UniqueJobSkipped::class, function (UniqueJobSkipped $event) use (&$alreadyQueued): void {
            if ($event->job instanceof EnrichSponsor) {
                $alreadyQueued++;
            }
        });

        foreach ($sponsors as $sponsor) {
            EnrichSponsor::dispatch($sponsor);
        }

        $queued = $sponsors->count() - $alreadyQueued;

        $this->info("Queued {$queued} ".str('sponsor')->plural($queued).' for lookup.');

        if ($alreadyQueued > 0) {
            $this->info("Left out {$alreadyQueued} ".str('sponsor')->plural($alreadyQueued).' already queued.');
        }

        return self::SUCCESS;
    }

    /**
     * The --limit option, or the configured batch size. Null when the option is not a positive whole number.
     */
    private function limit(): ?int
    {
        $option = $this->option('limit');

        if ($option === null || $option === '') {
            return max(1, (int) config('sponsor-finder.batch_size'));
        }

        $limit = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $limit === false ? null : $limit;
    }
}
