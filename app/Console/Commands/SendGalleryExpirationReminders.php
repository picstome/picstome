<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use Illuminate\Console\Command;

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

        Gallery::expiringSoon($days)
            ->reminderNotSent()
            ->cursor()
            ->each(function ($gallery) {
                $gallery->sendExpirationReminder();
            });

        return 0;
    }
}
