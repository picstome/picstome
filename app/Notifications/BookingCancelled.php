<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cancellation notice for the client.
 *
 * Refunds are not automatic: if money changed hands, the photographer has to
 * return it. The email says so instead of promising a refund the app cannot
 * perform yet.
 */
class BookingCancelled extends Notification
{
    use FormatsBookingEmail;
    use Queueable;

    public function __construct(public Booking $booking) {}

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
            ->subject(__('Booking cancelled: :name', ['name' => $booking->product_name]))
            ->greeting(__('Hello!'))
            ->line(__('Your session has been cancelled.'))
            ->line($this->row(__('Session'), $booking->product_name))
            ->line($this->row(__('Date'), $startsAt->isoFormat('LL')))
            ->line($this->row(__('Time'), $startsAt->format('H:i').' - '.$endsAt->format('H:i')));

        if (filled($booking->cancellation_reason)) {
            $mail->line(__('Reason: :reason', ['reason' => $booking->cancellation_reason]));
        }

        if (filled($booking->policy_snapshot)) {
            $mail->line(__('Cancellation policy: :policy', ['policy' => $booking->policy_snapshot]));
        }

        if ($booking->payments()->exists()) {
            $mail->line(__('If a refund applies, the photographer will handle it separately.'));
        }

        $mail->action(__('Book another session'), route('handle.show', ['handle' => $booking->team->handle]));

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
        ];
    }
}
