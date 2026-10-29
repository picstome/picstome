<?php

namespace App\Console\Commands;

use App\Models\GalleryArchive;
use App\Services\GalleryArchiveService;
use Illuminate\Console\Command;

/**
 * Removes expired gallery ZIPs and old archive rows.
 *
 * The scheduled run uses --force. Manual runs are dry by default unless
 * --force is provided, so an operator can inspect the scope first.
 */
class PruneGalleryArchivesCommand extends Command
{
    protected $signature = 'gallery-archives:prune
        {--dry-run : Show what would be removed without changing anything}
        {--force : Delete expired archives and old rows}';

    protected $description = 'Delete expired gallery ZIP archives and old archive rows.';

    public function handle(GalleryArchiveService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Use --dry-run to inspect the scope or --force to delete archives.');

            return self::FAILURE;
        }

        $now = now();
        $expired = GalleryArchive::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->whereIn('status', ['pending', 'building', 'ready'])
            ->get();

        $stuck = GalleryArchive::query()
            ->where('status', 'building')
            ->whereNotNull('started_at')
            ->where('started_at', '<', $now->copy()->subSeconds(
                (int) config('picstome.gallery_archives.timeout', 3600) + 300
            ))
            ->get()
            ->reject(fn (GalleryArchive $archive) => $expired->contains('id', $archive->id));

        $oldRows = GalleryArchive::query()
            ->whereIn('status', ['expired', 'failed'])
            ->where('updated_at', '<', $now->copy()->subDays(7))
            ->get();

        $this->line('Expired archives: '.$expired->count());
        $this->line('Stuck builds: '.$stuck->count());
        $this->line('Old archive rows: '.$oldRows->count());

        if ($dryRun) {
            $this->info('Dry run: nothing was deleted.');

            return self::SUCCESS;
        }

        $expired->merge($stuck)->each(function (GalleryArchive $archive) use ($service): void {
            $service->expire($archive, deleteNow: true);
        });

        $oldRows->each(function (GalleryArchive $archive) use ($service): void {
            $service->delete($archive, deleteNow: true);
        });

        $this->info('Gallery archive cleanup completed.');

        return self::SUCCESS;
    }
}
