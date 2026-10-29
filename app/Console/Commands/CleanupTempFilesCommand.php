<?php

namespace App\Console\Commands;

use App\Traits\FormatsFileSize;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deletes stale temporary files left behind by interrupted uploads, killed
 * archive workers and failed photo processing.
 *
 * Manual runs require --dry-run or --force. The scheduled run uses --force.
 */
class CleanupTempFilesCommand extends Command
{
    use FormatsFileSize;

    protected $signature = 'temp:cleanup
        {--dry-run : Show what would be removed without changing anything}
        {--force : Delete stale temporary files}';

    protected $description = 'Delete stale upload, gallery archive and photo temporary files.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Use --dry-run to inspect the scope or --force to delete temporary files.');

            return self::FAILURE;
        }

        if (! config('picstome.temp_cleanup.enabled', true)) {
            $this->info('Temporary file cleanup is disabled.');

            return self::SUCCESS;
        }

        $groups = $this->staleFilesByGroup();
        $total = 0;
        $totalBytes = 0;

        foreach ($groups as $label => $files) {
            $bytes = array_sum(array_column($files, 'size'));
            $this->line(sprintf('%s: %d file(s), %s', $label, count($files), $this->formatFileSize($bytes)));
            $total += count($files);
            $totalBytes += $bytes;
        }

        if ($dryRun) {
            $this->info("Dry run: nothing was deleted ({$total} file(s), {$this->formatFileSize($totalBytes)}).");

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;
        $freed = 0;

        foreach ($groups as $files) {
            foreach ($files as $file) {
                if (! @unlink($file['path'])) {
                    $failed++;
                    Log::warning('Could not delete a stale temporary file.', ['path' => $file['path']]);

                    continue;
                }

                $deleted++;
                $freed += $file['size'];
            }
        }

        $this->info(sprintf('Deleted %d file(s), freed %s.', $deleted, $this->formatFileSize($freed)));

        if ($failed > 0) {
            $this->warn("Could not delete {$failed} file(s). Check that the command user owns them or run the cleanup as the web server user.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{path: string, size: int}>>
     */
    protected function staleFilesByGroup(): array
    {
        $now = now();

        return [
            'PHP upload temporary files' => $this->staleUploadFiles(
                $now->copy()->subHours((int) config('picstome.temp_cleanup.upload_max_age_hours', 24)),
            ),
            'Gallery archive temporary files' => $this->staleFilesInPaths(
                array_unique(array_filter([
                    (string) config('picstome.gallery_archives.temp_path', ''),
                    (string) config('picstome.temp_cleanup.legacy_archive_path', sys_get_temp_dir()),
                ])),
                'gallery-archive-*',
                $now->copy()->subHours((int) config('picstome.temp_cleanup.archive_max_age_hours', 6)),
            ),
            'Photo processing temporary files' => $this->staleFiles(
                (string) config('picstome.temp_cleanup.photo_path', storage_path('app/photo-processing-temp')),
                '*',
                $now->copy()->subHours((int) config('picstome.temp_cleanup.photo_max_age_hours', 6)),
            ),
        ];
    }

    /**
     * @return array<int, array{path: string, size: int}>
     */
    protected function staleUploadFiles(CarbonInterface $olderThan): array
    {
        $files = $this->staleFiles(
            (string) config('picstome.temp_cleanup.upload_path', sys_get_temp_dir()),
            'php*',
            $olderThan,
        );

        // PHP upload temp files have no extension. Skip logs, scripts, sockets
        // and configuration files that share the php prefix.
        return array_values(array_filter(
            $files,
            fn (array $file): bool => ! str_contains(basename($file['path']), '.'),
        ));
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{path: string, size: int}>
     */
    protected function staleFilesInPaths(array $paths, string $pattern, CarbonInterface $olderThan): array
    {
        $files = [];

        foreach ($paths as $path) {
            foreach ($this->staleFiles($path, $pattern, $olderThan) as $file) {
                $files[$file['path']] = $file;
            }
        }

        return array_values($files);
    }

    /**
     * @return array<int, array{path: string, size: int}>
     */
    protected function staleFiles(string $path, string $pattern, CarbonInterface $olderThan): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $matches = glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$pattern) ?: [];

        return array_values(array_filter(array_map(function (string $file) use ($olderThan): ?array {
            if (is_link($file) || ! is_file($file)) {
                return null;
            }

            $modifiedAt = @filemtime($file);

            if ($modifiedAt === false || $modifiedAt >= $olderThan->getTimestamp()) {
                return null;
            }

            return ['path' => $file, 'size' => (int) @filesize($file)];
        }, $matches)));
    }
}
