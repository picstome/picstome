<?php

namespace App\Listeners;

use App\Events\PhotoAdded;
use App\Jobs\ProcessPhoto;

class SchedulePhotoProcessing
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PhotoAdded $event): void
    {
        ProcessPhoto::dispatch($event->photo);
    }
}
