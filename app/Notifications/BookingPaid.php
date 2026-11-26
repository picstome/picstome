<?php

namespace App\Notifications;

use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Notifications\Concerns\IncludesMagicLoginLink;
use App\Services\Booking\BookingCalendarLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Cashier;

/**
 * The photographer's copy of a paid booking.
 *
 * It is intentionally different from the client's confirmation: a subject
 * that starts with "New booking" and a CTA to the agenda. Sending the exact
 * same message to two recipients in the same second gets filtered or lost in
 * some mail providers, and this copy is actionable for the photographer.
 */
class BookingPaid extends Notification
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

        $paidNow = $booking->payment_kind === PaymentKind::Deposit
            ? $booking->deposit_amount
            : $booking->total_amount;
        $pending = max(0, $booking->total_amount - $paidNow);

        $mail = (new MailMessage)
            ->subject(__('New booking paid: :name', ['name' => $booking->product_name]))
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
            ->line($this->row(__('Total'), $this->money($booking->total_amount)))
            ->line($this->row(__('Paid now'), $this->money($paidNow)));

        if ($pending > 0) {
            $mail->line($this->row(__('Pending'), $this->money($pending)));
        }

        if (filled($booking->notes)) {
            $mail->line(__('Notes: :notes', ['notes' => $booking->notes]));
        }

        $links = app(BookingCalendarLinks::class);

        $mail->line(__('Add it to your calendar:'))
            ->line(
                '['.__('Google Calendar').']('.$links->google($booking).') · '
                .'['.__('Outlook Calendar').']('.$links->outlook($booking).') · '
                .'['.__('iCloud Calendar').']('.$links->ics($booking).')'
            );

        $payment = $booking->payments()
            ->whereIn('payment_kind', [PaymentKind::Full->value, PaymentKind::Deposit->value])
            ->latest('id')
            ->first();

        $invoice = $payment?->invoice()->first();

        if ($invoice !== null) {
            $mail->line(__('Invoice: :number', ['number' => $invoice->full_number ?? __('Draft')]));
            $mail->action(__('Review invoice'), route('invoices.show', ['invoice' => $invoice]));
            $mail->line('['.__('Open the agenda').']('.route('bookings', ['view' => 'agenda']).')');
        } else {
            $mail->action(__('Open the agenda'), route('bookings', ['view' => 'agenda']));
        }

        return $this->addMagicLoginLink($mail, $notifiable, route('bookings', ['view' => 'agenda'], absolute: false));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
        ];
    }

    private function money(int $minorUnits): string
    {
        return Cashier::formatAmount($minorUnits, $this->booking->currency);
    }
}
