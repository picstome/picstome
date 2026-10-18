<?php

namespace App\Console\Commands;

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use Illuminate\Console\Command;

class IngestFtpUploadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ingest:ftp-uploads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest camera photos uploaded via FTP/SFTP into the galleries receiving them.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $stabilityCutoff = now()->subSeconds((int) config('picstome.ftp_uploads.stability_seconds', 60));
        $dispatched = 0;

        FtpAccount::query()
            ->where('active', true)
            ->with('team')
            ->get()
            ->each(function (FtpAccount $account) use ($stabilityCutoff, &$dispatched) {
                if (! $account->team) {
                    return;
                }

                $disk = FtpAccount::disk();

                foreach ($disk->files($account->inboxPath()) as $path) {
                    // Skip files that may still be transferring.
                    if ($disk->lastModified($path) > $stabilityCutoff->getTimestamp()) {
                        continue;
                    }

                    IngestFtpUpload::dispatch($account->id, $path);

                    $dispatched++;
                }
            });

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} FTP upload(s) for ingestion.");
        }
    }
}
