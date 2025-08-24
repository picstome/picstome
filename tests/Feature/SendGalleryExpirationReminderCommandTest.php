<?php

use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use App\Notifications\GalleryExpirationReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

describe('SendGalleryExpirationReminderCommand', function () {
    beforeEach(function () {
        Notification::fake();
        $this->user = User::factory()->create();
        $this->team = Team::factory()->for($this->user, 'owner')->create();
    });

    it('sends a reminder if the gallery expires soon', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => now()->addDays(2),
        ]);

        artisan('galleries:send-expiration-reminders')->assertExitCode(0);

        Notification::assertSentTo(
            $this->team->owner,
            GalleryExpirationReminder::class,
            function ($notification, $channels) use ($gallery) {
                        return true;
            }
        );
    });

    it('does not send a reminder if the gallery does not expire soon', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => now()->addDays(10),
        ]);

        artisan('galleries:send-expiration-reminders')->assertExitCode(0);

        Notification::assertNotSentTo(
            $this->team->owner,
            GalleryExpirationReminder::class
        );
    });

    it('does not send a reminder if the gallery has no expiration date', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => null,
        ]);

        artisan('galleries:send-expiration-reminders')->assertExitCode(0);

        Notification::assertNotSentTo(
            $this->team->owner,
            GalleryExpirationReminder::class
        );
    });

    it('does not send duplicate reminders for the same gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => now()->addDays(2),
        ]);

        $gallery->reminder_sent_at = now();
        $gallery->save();

        artisan('galleries:send-expiration-reminders')->assertExitCode(0);

        Notification::assertNotSentTo(
            $this->team->owner,
            GalleryExpirationReminder::class
        );
    });
});
