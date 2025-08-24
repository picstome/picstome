<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gallery;
use App\Notifications\GalleryExpirationReminder;

class SendGalleryExpirationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galleries:send-expiration-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = config('picstome.gallery_expiration_reminder_days', 3);
        $galleries = Gallery::expiringSoon($days)
            ->reminderNotSent()
            ->get();

        foreach ($galleries as $gallery) {
            $owner = $gallery->team?->owner;
            if ($owner) {
                $owner->notify(new GalleryExpirationReminder($gallery));
                $gallery->reminder_sent_at = now();
                $gallery->save();
            }
        }
        return 0;
    }
}
