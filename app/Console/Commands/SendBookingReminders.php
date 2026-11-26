<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\BookingSetting;
use App\Notifications\BookingReminder as BookingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the pre-session reminders configured by each photographer.
 *
 * The reminder is claimed in `booking_reminders` before sending, so running the
 * command twice (or from two workers) never duplicates an email.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send the configured reminders for upcoming confirmed bookings';

    public function handle(): int
    {
        $now = now();

        $maxHours = max(
            72,
            (int) BookingSetting::query()->max('reminder_first_hours'),
            (int) BookingSetting::query()->max('reminder_second_hours'),
        );

        $sent = 0;

        Booking::query()
            ->with(['product', 'team.bookingSetting'])
            ->where('status', BookingStatus::Confirmed->value)
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->copy()->addHours($maxHours + 1))
            ->orderBy('starts_at')
            ->chunkById(100, function ($bookings) use ($now, &$sent) {
                foreach ($bookings as $booking) {
                    $settings = $booking->team->bookingSetting
                        ?? BookingSetting::forTeam($booking->team);

                    $reminders = [
                        ['type' => 'reminder_first', 'enabled' => $settings->reminder_first_enabled, 'hours' => $settings->reminder_first_hours],
                        ['type' => 'reminder_second', 'enabled' => $settings->reminder_second_enabled, 'hours' => $settings->reminder_second_hours],
                    ];

                    foreach ($reminders as $reminder) {
                        if (! $reminder['enabled'] || $reminder['hours'] < 1) {
                            continue;
                        }

                        $dueAt = $booking->starts_at->copy()->subHours($reminder['hours']);

                        if ($now->lessThan($dueAt)) {
                            continue;
                        }

                        // A booking made after the reminder moment does not get
                        // a late "72 hours before" email: the confirmation
                        // already reached the client.
                        if ($booking->created_at->greaterThan($dueAt)) {
                            continue;
                        }

                        $claimed = BookingReminder::query()->insertOrIgnore([
                            'booking_id' => $booking->id,
                            'type' => $reminder['type'],
                            'sent_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        if ($claimed === 0) {
                            continue;
                        }

                        Notification::route('mail', $booking->customer_email)
                            ->notify(new BookingReminderNotification($booking, $reminder['type']));

                        $sent++;
                    }
                }
            });

        $this->info("Booking reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
