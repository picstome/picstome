<?php

namespace App\Notifications;

use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Cashier;

/**
 * Instructions for a booking paid by Bizum. Nothing is confirmed yet: the
 * photographer checks the transfer and confirms it from the calendar.
 */
class BookingPendingBizum extends Notification
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
        $team = $booking->team;
        $timezone = $booking->timezone;
        $startsAt = $booking->starts_at->setTimezone($timezone);
        $endsAt = $booking->ends_at->setTimezone($timezone);

        $amount = $booking->payment_kind === PaymentKind::Deposit
            ? $booking->deposit_amount
            : $booking->total_amount;

        $mail = (new MailMessage)
            ->subject(__('Complete your booking with Bizum: :name', ['name' => $booking->product_name]))
            ->greeting(__('Hello!'))
            ->line(__('Your slot is reserved. Send the payment to confirm it:'))
            ->line($this->row(__('Session'), $booking->product_name))
            ->line($this->row(__('Date'), $startsAt->isoFormat('LL')))
            ->line($this->row(__('Time'), $startsAt->format('H:i').' - '.$endsAt->format('H:i')))
            ->line($this->row(__('Amount'), Cashier::formatAmount($amount, $booking->currency)))
            ->line(__('Send the Bizum to this phone:'))
            ->line('### '.($team->bizum_phone ?: '—'))
            ->line($this->row(__('Reference'), $booking->bizumReference()));

        if (filled($team->bizum_instructions)) {
            $mail->line($team->bizum_instructions);
        }

        $mail->line(__('The reservation is confirmed as soon as the photographer checks the payment.'));

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
