<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\InvalidRegisterFile;
use App\Exceptions\RegisterDownloadFailed;
use App\Models\Team;
use App\Services\RegisterDownload;
use App\Services\RegisterImport;
use Illuminate\Console\Command;

class ImportSponsors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sponsors:import
        {file? : Path to the register CSV, or a file name in storage/app/imports. Leave out to download the latest from GOV.UK}
        {--team= : Slug of the team to import into}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the sponsor register CSV into a team';

    /**
     * Execute the console command.
     */
    public function handle(RegisterImport $import, RegisterDownload $download): int
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

        $file = $this->argument('file');

        if (is_string($file) && $file !== '') {
            $path = $this->path($file);
        } else {
            try {
                $path = $this->latestRegister($download);
            } catch (RegisterDownloadFailed $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $bar = $this->output->createProgressBar();

        try {
            $summary = $import->import($team, $path, fn () => $bar->advance());
        } catch (InvalidRegisterFile $exception) {
            $this->newLine();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Result', 'Count'], [
            ['Register rows read', $summary->read],
            ['Ignored (other routes)', $summary->ignored],
            ['New sponsors', $summary->new],
            ['Existing sponsors', $summary->existing],
            ['Moved to skipped (B rating)', $summary->movedToSkipped],
            ['Moved back to pending (A rating)', $summary->movedToPending],
        ]);

        return self::SUCCESS;
    }

    /**
     * Download the latest register into storage/app/imports, or reuse it if it is already there.
     *
     * @throws RegisterDownloadFailed
     */
    private function latestRegister(RegisterDownload $download): string
    {
        $this->info('Looking for the latest register on GOV.UK...');

        $register = $download->latest(storage_path('app/imports'));

        $this->info(($register->downloaded ? 'Downloaded ' : 'Already downloaded, using ').basename($register->path));

        return $register->path;
    }

    /**
     * Use the path as given when it exists, otherwise look in storage/app/imports.
     */
    private function path(string $file): string
    {
        return is_file($file) ? $file : storage_path('app/imports/'.$file);
    }
}
