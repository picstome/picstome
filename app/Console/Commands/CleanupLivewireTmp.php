<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupLivewireTmp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:livewire-tmp {disk=s3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete S3 livewire-tmp files older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Import Storage and Carbon
        /** @var \Illuminate\Contracts\Filesystem\Filesystem $disk */
        $disk = Storage::disk('s3');
        $folder = 'livewire-tmp';
        $now = Carbon::now();
        $deleted = 0;
        $kept = 0;

        $files = $disk->allFiles($folder);

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);

            if ($now->diffInSeconds(Carbon::createFromTimestamp($lastModified)) > 86400) {
                $disk->delete($file);

                $this->info("Deleted: $file");

                $deleted++;
            } else {
                $kept++;
            }
        }

        $this->info("Deleted $deleted files. Kept $kept files.");
    }
}
