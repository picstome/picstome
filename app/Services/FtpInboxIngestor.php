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
            ->whereNull('quota_paused_at')
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
        // A paused account has no storage left: scanning its inbox would only
        // queue jobs that immediately discard the files again.
        if ($account->isQuotaPaused()) {
            return 0;
        }

        return (int) Cache::lock("ftp-account:{$account->id}:scan", 30)
            ->get(function () use ($account, $stabilitySeconds): int {
                $disk = FtpAccount::disk();
                $cutoff = now()->subSeconds($stabilitySeconds)->getTimestamp();
                $dispatched = 0;

                // Cameras may upload into subfolders (for example queen/ or
                // DCIM/100CANON/), so the inbox has to be listed recursively.
                // Reading LastModified from the listing avoids one HEAD per
                // file.
                foreach ($disk->getDriver()->listContents($account->inboxPath(), true) as $item) {
                    if (! $item->isFile()) {
                        continue;
                    }

                    $lastModified = $item->lastModified();

                    if ($lastModified === null) {
                        try {
                            $lastModified = $disk->lastModified($item->path());
                        } catch (Throwable) {
                            continue;
                        }
                    }

                    if ($lastModified > $cutoff) {
                        continue;
                    }

                    IngestFtpUpload::dispatch($account->id, $item->path());

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
