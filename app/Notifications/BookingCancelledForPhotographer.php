<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Notifications\Concerns\IncludesMagicLoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cancellation record for the photographer, with the client's contact details
 * so a refund or a reschedule can be arranged from the inbox.
 */
class BookingCancelledForPhotographer extends Notification
{
    use FormatsBookingEmail;
    use IncludesMagicLoginLink, Queueable;

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
            ->subject(__('Booking cancelled by you: :name', ['name' => $booking->product_name]))
            ->greeting(__('Booking cancelled'))
            ->line($this->row(__('Customer'), $booking->customer_name))
            ->line($this->row(__('Email'), $booking->customer_email));

        if (filled($booking->customer_phone)) {
            $mail->line($this->row(__('Phone'), $booking->customer_phone));
        }

        $mail
            ->line($this->row(__('Session'), $booking->product_name))
            ->line($this->row(__('Date'), $startsAt->isoFormat('LL')))
            ->line($this->row(__('Time'), $startsAt->format('H:i').' - '.$endsAt->format('H:i')));

        if (filled($booking->cancellation_reason)) {
            $mail->line(__('Reason: :reason', ['reason' => $booking->cancellation_reason]));
        }

        $mail->action(__('Open the agenda'), route('bookings', ['view' => 'agenda']));

        return $this->addMagicLoginLink($mail, $notifiable, route('bookings', ['view' => 'agenda'], absolute: false));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
        ];
    }
}
