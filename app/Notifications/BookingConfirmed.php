<?php

namespace App\Notifications;

use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingEmail;
use App\Notifications\Concerns\IncludesMagicLoginLink;
use App\Services\Booking\BookingCalendarFile;
use App\Services\Booking\BookingCalendarLinks;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Cashier;

/**
 * Confirmation sent to the client and to the photographer once a booking is
 * paid. Carries the .ics file so the session lands in any calendar without
 * relying on a specific provider.
 */
class BookingConfirmed extends Notification
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
            ->subject(__('Booking confirmed: :name', ['name' => $booking->product_name]))
            ->greeting(__('Hello!'))
            ->line(__('Your session is booked.'))
            ->line($this->row(__('Session'), $booking->product_name))
            ->line($this->row(__('Date'), $startsAt->isoFormat('LL')))
            ->line($this->row(__('Time'), $startsAt->format('H:i').' - '.$endsAt->format('H:i')))
            ->line($this->row(__('Total'), $this->money($booking->total_amount)))
            ->line($this->row(__('Paid now'), $this->money($paidNow)));

        if ($pending > 0) {
            $mail->line($this->row(__('Pending'), $this->money($pending)));
        }

        if (filled($booking->policy_snapshot)) {
            $mail->line(__('Cancellation policy: :policy', ['policy' => $booking->policy_snapshot]));
        }

        $links = app(BookingCalendarLinks::class);

        $mail->line(__('Add it to your calendar:'))
            ->line(
                '['.__('Google Calendar').']('.$links->google($booking).') · '
                .'['.__('Outlook Calendar').']('.$links->outlook($booking).') · '
                .'['.__('iCloud Calendar').']('.$links->ics($booking).')'
            );

        $mail
            ->action(__('View booking'), route('handle.book.success', [
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

        return $this->addMagicLoginLink($mail, $notifiable, route('bookings', absolute: false));
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
