<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use Illuminate\Console\Command;

class DeleteExpiredGalleriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galleries:delete-expired {--dry-run} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete galleries whose expiration date has passed.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Use --dry-run to inspect the scope or --force to delete expired galleries.');

            return self::FAILURE;
        }

        $this->line('Expired galleries: '.Gallery::expired()->count());

        if ($dryRun) {
            $this->info('Dry run: nothing was deleted.');

            return self::SUCCESS;
        }

        Gallery::expired()->cursor()->each(function (Gallery $gallery) {
            $gallery->deletePhotos();
            $gallery->delete();
        });

        $this->info('Completed deleting expired galleries.');

        return self::SUCCESS;
    }
}
