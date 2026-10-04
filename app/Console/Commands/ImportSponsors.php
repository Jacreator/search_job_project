<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\InvalidRegisterFile;
use App\Exceptions\RegisterDownloadFailed;
use App\Models\Team;
use App\Services\RegisterDownload;
use App\Services\RegisterImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            $path = $this->latestRegister($download);

            if ($path === null) {
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
     * If the download fails, fall back to the newest register already saved there.
     */
    private function latestRegister(RegisterDownload $download): ?string
    {
        $directory = storage_path('app/imports');

        $this->info('Looking for the latest register on GOV.UK...');

        try {
            $register = $download->latest($directory);
        } catch (RegisterDownloadFailed $exception) {
            return $this->savedRegister($download, $directory, $exception);
        }

        $this->info(($register->downloaded ? 'Downloaded ' : 'Already downloaded, using ').basename($register->path));

        return $register->path;
    }

    /**
     * The newest saved register after a failed download, or null (with an error) when there is none.
     */
    private function savedRegister(RegisterDownload $download, string $directory, RegisterDownloadFailed $exception): ?string
    {
        $path = $download->newestSaved($directory);

        if ($path === null) {
            $this->error($exception->getMessage());
            $this->error('No saved register-*.csv was found in storage/app/imports either.');

            return null;
        }

        $this->warn($exception->getMessage());
        $this->warn('Importing the newest saved register instead: '.basename($path));

        Log::warning('Sponsor register download failed, imported a saved register instead.', [
            'reason' => $exception->getMessage(),
            'file' => basename($path),
        ]);

        return $path;
    }

    /**
     * Use the path as given when it exists, otherwise look in storage/app/imports.
     */
    private function path(string $file): string
    {
        return is_file($file) ? $file : storage_path('app/imports/'.$file);
    }
}
