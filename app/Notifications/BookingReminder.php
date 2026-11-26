<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Services\Booking\BookingCalendarFile;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pre-session reminder for the client.
 *
 * The same class handles both configured reminders; the wording adapts to the
 * date, so changing 72/24 hours in the settings does not need another email.
 */
class BookingReminder extends Notification
{
    use FormatsBookingEmail;
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $kind,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $timezone = $booking->timezone;
        $startsAt = $booking->starts_at->setTimezone($timezone);
        $endsAt = $booking->ends_at->setTimezone($timezone);

        $mail = (new MailMessage)
            ->subject(__('Reminder: your session is on :date', ['date' => $startsAt->isoFormat('LL')]))
            ->greeting(__('Hello!'))
            ->line(__('This is a reminder of your session.'))
            ->line($this->row(__('Session'), $booking->product_name))
            ->line($this->row(__('Date'), $startsAt->isoFormat('LL')))
            ->line($this->row(__('Time'), $startsAt->format('H:i').' - '.$endsAt->format('H:i')));

        if (filled($booking->product?->location_name)) {
            $mail->line($this->row(__('Location'), $booking->product->location_name));
        }

        if (filled($booking->policy_snapshot)) {
            $mail->line(__('Cancellation policy: :policy', ['policy' => $booking->policy_snapshot]));
        }

        $mail
            ->action(__('Add to my calendar'), route('handle.book.ics', [
                'handle' => $booking->team->handle,
                'booking' => $booking->ulid,
            ]))
            ->attachData(
                app(BookingCalendarFile::class)->for($booking),
                'session.ics',
                ['mime' => 'text/calendar'],
            );

        if ($notifiable instanceof AnonymousNotifiable) {
            MailBranding::forTeam($booking->team)->applyTo($mail);
        }

        if ($booking->team->subscribed()) {
            $mail->salutation($booking->team->name);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'kind' => $this->kind,
        ];
    }
}
