<?php

namespace App\Listeners;

use App\Events\PhotoAdded;

class NotifyStorageAlmostFull
{
    /**
     * Check the team after every new photo, version or FTP upload.
     */
    public function handle(PhotoAdded $event): void
    {
        $event->photo->gallery?->team?->notifyOwnerWhenStorageAlmostFull();
    }
}
