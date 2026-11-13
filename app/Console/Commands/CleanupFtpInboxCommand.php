<?php

namespace App\Console\Commands;

use App\Models\FtpAccount;
use App\Traits\FormatsFileSize;
use Illuminate\Console\Command;

/**
 * Deletes files that have been sitting in camera inboxes for too long.
 *
 * The ingestor only moves files once they are stable, and the paused-account
 * path deletes new arrivals. This command cleans up leftovers: transfers that
 * were interrupted, accounts that were deactivated, and files that piled up
 * before the pause rule existed. They are stored (and paid for) without ever
 * reaching a gallery.
 */
class CleanupFtpInboxCommand extends Command
{
    use FormatsFileSize;

    protected $signature = 'ftp:cleanup-inbox
        {--dry-run : Show what would be removed without changing anything}
        {--force : Delete the stale inbox files}';

    protected $description = 'Delete stale files left in camera upload inboxes.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Use --dry-run to inspect the scope or --force to delete inbox files.');

            return self::FAILURE;
        }

        $disk = FtpAccount::disk();

        // Same stability window the ingestor uses: a camera may still be
        // writing the file, and deleting it mid-transfer loses the shot.
        $cutoff = now()->subSeconds(
            (int) config('picstome.ftp_uploads.stability_seconds', 60)
        )->getTimestamp();

        $total = 0;
        $bytes = 0;

        foreach (FtpAccount::query()->with('team')->cursor() as $account) {
            foreach ($disk->getDriver()->listContents($account->inboxPath(), true) as $item) {
                if (! $item->isFile()) {
                    continue;
                }

                $lastModified = $item->lastModified();

                if ($lastModified === null || $lastModified > $cutoff) {
                    continue;
                }

                $total++;
                $bytes += (int) ($item->fileSize() ?? 0);

                if (! $dryRun) {
                    $disk->delete($item->path());
                }
            }
        }

        $label = $dryRun ? 'Found' : 'Deleted';

        $this->info(sprintf('%s %d stale inbox file(s), %s.', $label, $total, $this->formatFileSize($bytes)));

        return self::SUCCESS;
    }
}
