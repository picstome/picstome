<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Throwable;

/**
 * Deletes stale Livewire temporary uploads from the disk Livewire is actually
 * using.
 *
 * Livewire creates temporary uploads before the application moves them to
 * permanent storage. Abandoned uploads have to be removed eventually or they
 * accumulate in the bucket and increase storage costs.
 */
class CleanupLivewireTmp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:livewire-tmp
        {disk? : Filesystem disk to clean (defaults to the Livewire temporary upload disk)}
        {--hours=24 : Delete temporary uploads older than this many hours}
        {--dry-run : Show what would be deleted without removing anything}
        {--force : Delete stale temporary uploads}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete stale Livewire temporary uploads from the configured disk.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Use --dry-run to inspect the scope or --force to delete stale temporary uploads.');

            return self::FAILURE;
        }

        if (! config('livewire.temporary_file_upload.cleanup', true)) {
            $this->info('Livewire temporary upload cleanup is disabled.');

            return self::SUCCESS;
        }

        $diskName = $this->argument('disk') ?: FileUploadConfiguration::disk();
        $directory = FileUploadConfiguration::directory();
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours)->getTimestamp();

        try {
            $disk = Storage::disk($diskName);
        } catch (Throwable $exception) {
            $this->error("Filesystem disk [{$diskName}] is not configured: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $deleted = 0;
        $kept = 0;
        $failed = 0;
        $oldest = null;
        $newest = null;

        try {
            // Read LastModified from the listing itself. Calling lastModified()
            // per object would issue thousands of HEAD requests and can abort
            // the cleanup when an upload is stored between the listing and the
            // HEAD (404).
            foreach ($disk->getDriver()->listContents($directory, true) as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $lastModified = $item->lastModified();

                if ($lastModified === null) {
                    try {
                        $lastModified = $disk->lastModified($item->path());
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->logFailure('read the last modified date of', $diskName, $item->path(), $exception);

                        continue;
                    }
                }

                $oldest = $oldest === null ? $lastModified : min($oldest, $lastModified);
                $newest = $newest === null ? $lastModified : max($newest, $lastModified);

                // Compare timestamps directly: Carbon 3's diffInSeconds()
                // returns a signed value, so the previous implementation kept
                // every file because the difference was negative.
                if ($lastModified >= $cutoff) {
                    $kept++;

                    continue;
                }

                if ($dryRun) {
                    $deleted++;

                    if ($this->output->isVerbose() || $deleted <= 10) {
                        $this->line("Would delete: {$item->path()}");
                    }

                    continue;
                }

                try {
                    if (! $disk->delete($item->path())) {
                        $failed++;
                        Log::warning('Could not delete a Livewire temporary upload.', [
                            'disk' => $diskName,
                            'path' => $item->path(),
                        ]);

                        continue;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $this->logFailure('delete', $diskName, $item->path(), $exception);

                    continue;
                }

                $deleted++;

                if ($this->output->isVerbose()) {
                    $this->line("Deleted: {$item->path()}");
                }
            }
        } catch (Throwable $exception) {
            $this->error("Could not list Livewire temporary uploads on disk [{$diskName}]: {$exception->getMessage()}");

            Log::error('Could not list Livewire temporary uploads.', [
                'disk' => $diskName,
                'directory' => $directory,
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }

        $summary = [
            'disk' => $diskName,
            'directory' => $directory,
            'hours' => $hours,
            'deleted' => $deleted,
            'kept' => $kept,
            'failed' => $failed,
            'oldest' => $oldest === null ? null : now()->setTimestamp($oldest)->toIso8601String(),
            'newest' => $newest === null ? null : now()->setTimestamp($newest)->toIso8601String(),
        ];

        $this->info($dryRun
            ? "Dry run: would delete {$deleted} file(s), kept {$kept} file(s), {$failed} failed."
            : "Deleted {$deleted} file(s), kept {$kept} file(s), {$failed} failed.");

        if ($oldest !== null) {
            $this->line("Oldest upload: {$summary['oldest']} | Newest upload: {$summary['newest']}");
        }

        Log::info('Livewire temporary upload cleanup completed.', $summary);

        return self::SUCCESS;
    }

    private function logFailure(string $action, string $disk, string $path, Throwable $exception): void
    {
        Log::warning("Could not {$action} a Livewire temporary upload.", [
            'disk' => $disk,
            'path' => $path,
            'error' => $exception->getMessage(),
        ]);
    }
}
