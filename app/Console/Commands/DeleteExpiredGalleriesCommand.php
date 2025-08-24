<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gallery;

class DeleteExpiredGalleriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galleries:delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete galleries whose expiration date has passed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Gallery::whereNotNull('expiration_date')
            ->where('expiration_date', '<', now())
            ->get()
            ->each(function ($gallery) {
                $gallery->deletePhotos();
                $gallery->delete();
            });

        $this->info('Completed deleting expired galleries.');
    }
}
