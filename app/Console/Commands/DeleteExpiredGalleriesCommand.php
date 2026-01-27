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
        Gallery::expired()->get()->each(function ($gallery) {
            $gallery->deletePhotos();
            $gallery->delete();
        });

        $this->info('Completed deleting expired galleries.');
    }
}
