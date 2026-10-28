<?php

namespace App\Console\Commands;

use App\Services\FtpInboxIngestor;
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
    public function handle(FtpInboxIngestor $ingestor): void
    {
        $dispatched = $ingestor->dispatchAll();

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} FTP upload(s) for ingestion.");
        }
    }
}
