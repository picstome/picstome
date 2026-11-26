<?php

namespace App\Notifications;

use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Notifications\Concerns\IncludesMagicLoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Cashier;

/**
 * The photographer's copy of a new booking that is waiting for a Bizum.
 *
 * The client's copy has the payment instructions; this one carries the client
 * data and the reference so the photographer can confirm it from the agenda.
 */
class BookingPendingBizumForPhotographer extends Notification
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

        $expected = $booking->payment_kind === PaymentKind::Deposit
            ? $booking->deposit_amount
            : $booking->total_amount;

        $mail = (new MailMessage)
            ->subject(__('New booking pending payment: :name', ['name' => $booking->product_name]))
            ->greeting(__('New booking'))
            ->line($this->row(__('Customer'), $booking->customer_name))
            ->line($this->row(__('Email'), $booking->customer_email));

        if (filled($booking->customer_phone)) {
            $mail->line($this->row(__('Phone'), $booking->customer_phone));
        }

        $mail
            ->line($this->row(__('Session'), $booking->product_name))
            ->line($this->row(__('Date'), $startsAt->isoFormat('LL')))
            ->line($this->row(__('Time'), $startsAt->format('H:i').' - '.$endsAt->format('H:i')))
            ->line($this->row(__('Expected'), Cashier::formatAmount($expected, $booking->currency)))
            ->line($this->row(__('Reference'), strtoupper(substr($booking->ulid, -6))))
            ->line(__('Payment method: :method', ['method' => __('Bizum')]))
            ->line(__('Confirm it from the agenda when the payment arrives.'))
            ->action(__('Open the agenda'), route('bookings', ['view' => 'agenda']));

        return $this->addMagicLoginLink($mail, $notifiable, route('bookings', ['view' => 'agenda'], absolute: false));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
        ];
    }
}
