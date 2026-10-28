<?php

namespace App\Services;

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use Illuminate\Support\Facades\Cache;

/**
 * Lists the camera inbox and queues stable uploads for ingestion.
 *
 * The every-minute command, the open photographer gallery and the SFTPGo
 * webhook all use the same job, which is unique per account + path, so
 * overlapping scans cannot ingest the same file twice.
 */
class FtpInboxIngestor
{
    /**
     * Scan every active account. Used by the scheduled command.
     */
    public function dispatchAll(): int
    {
        $stabilitySeconds = (int) config('picstome.ftp_uploads.stability_seconds', 60);
        $dispatched = 0;

        FtpAccount::query()
            ->where('active', true)
            ->with('team')
            ->get()
            ->each(function (FtpAccount $account) use ($stabilitySeconds, &$dispatched): void {
                if (! $account->team) {
                    return;
                }

                $dispatched += $this->dispatchStableUploads($account, $stabilitySeconds);
            });

        return $dispatched;
    }

    /**
     * Scan one account, skipping files that may still be transferring.
     * The lock keeps a page-triggered scan and the cron from overlapping.
     */
    public function dispatchStableUploads(FtpAccount $account, int $stabilitySeconds): int
    {
        return (int) Cache::lock("ftp-account:{$account->id}:scan", 30)
            ->get(function () use ($account, $stabilitySeconds): int {
                $disk = FtpAccount::disk();
                $cutoff = now()->subSeconds($stabilitySeconds)->getTimestamp();
                $dispatched = 0;

                foreach ($disk->files($account->inboxPath()) as $path) {
                    $lastModified = $disk->lastModified($path);

                    if ($lastModified === false || $lastModified > $cutoff) {
                        continue;
                    }

                    IngestFtpUpload::dispatch($account->id, $path);

                    $dispatched++;
                }

                return $dispatched;
            });
    }

    /**
     * The stability window used while a photographer has the gallery open.
     * S3 only lists objects after the upload finishes, so it can be shorter.
     */
    public function liveScanStabilitySeconds(): int
    {
        if (FtpAccount::diskDriver() !== 's3') {
            return (int) config('picstome.ftp_uploads.stability_seconds', 60);
        }

        return max(0, (int) config('picstome.ftp_uploads.live_scan_stability_seconds', 2));
    }
}
