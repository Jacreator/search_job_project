<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SkipReason;
use App\Enums\SponsorStatus;
use App\Models\Sponsor;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SponsorStatusReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sponsors:status
        {--team= : Only the team with this slug (default all teams)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show sponsor counts per status, tech result, and skip reason';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Sponsor::query();

        $slug = trim((string) $this->option('team'));

        if ($slug !== '') {
            $team = Team::query()->where('slug', $slug)->first();

            if ($team === null) {
                $this->error("Team [{$slug}] does not exist.");

                return self::FAILURE;
            }

            $query->where('team_id', $team->id);
            $this->info("Team: {$team->name} ({$team->slug})");
        } else {
            $this->info('All teams');
        }

        $statuses = $this->countsBy($query, 'status');
        $this->table(['Status', 'Count'], array_map(
            fn (SponsorStatus $status) => [$status->value, $statuses[$status->value] ?? 0],
            SponsorStatus::cases(),
        ));

        $tech = $this->countsBy($query, 'is_tech');
        $this->table(['Tech', 'Count'], [
            ['yes', $tech[1] ?? 0],
            ['no', $tech[0] ?? 0],
            ['not checked', $tech[''] ?? 0],
        ]);

        $reasons = $this->countsBy($query->clone()->where('status', SponsorStatus::Skipped), 'skip_reason');
        $this->table(['Skip reason', 'Count'], [
            ...array_map(
                fn (SkipReason $reason) => [$reason->value, $reasons[$reason->value] ?? 0],
                SkipReason::cases(),
            ),
            ...(isset($reasons['']) ? [['none', $reasons['']]] : []),
        ]);

        return self::SUCCESS;
    }

    /**
     * Row counts keyed by the column value: strings as stored, booleans as 1 or 0, and '' for null.
     *
     * @param  Builder<Sponsor>  $query
     * @return array<int|string, int>
     */
    private function countsBy(Builder $query, string $column): array
    {
        $counts = [];

        $rows = $query->clone()->toBase()
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->get();

        foreach ($rows as $row) {
            $value = $row->{$column};
            $key = is_bool($value) || is_int($value) ? (int) $value : (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row->aggregate;
        }

        return $counts;
    }
}
