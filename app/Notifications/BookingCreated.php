<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Photoshoot;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreated extends Notification
{
    use Queueable;

    public function __construct(
        public Photoshoot $photoshoot,
        public ?Payment $payment = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->photoshoot->date?->format('F j, Y') ?? __('N/A');
        $name = $this->photoshoot->name ?? __('Session');
        $amount = $this->payment?->formattedAmount ?? ($this->payment?->amount ? ($this->payment->amount / 100).' '.strtoupper($this->payment->currency) : __('N/A'));
        $customer = $this->photoshoot->customer_name ?? __('N/A');

        $mail = (new MailMessage)
            ->subject(__('Booking Confirmed: :name', ['name' => $name]))
            ->greeting(__('Hello!'))
            ->line(__('A new booking has been made.'))
            ->line(__('Session: :name', ['name' => $name]))
            ->line(__('Date: :date', ['date' => $date]))
            ->line(__('Customer: :customer', ['customer' => $customer]))
            ->line(__('Amount: :amount', ['amount' => $amount]));

        if ($this->photoshoot->team) {
            $mail->line(__('Team: :team', ['team' => $this->photoshoot->team->name]));
        }

        $mail->salutation(__('Best regards, Picstome Team'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'photoshoot_id' => $this->photoshoot->id,
            'payment_id' => $this->payment?->id,
        ];
    }
}
