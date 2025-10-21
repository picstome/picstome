<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Photoshoot;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreated extends Notification
{
    use Queueable;

    public function __construct(
        public Photoshoot $photoshoot,
        public Carbon $date,
        public Carbon $startTime,
        public Carbon $endTime,
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->date->format('F j, Y');
        $name = $this->photoshoot->name ?? __('Session');
        $amount = $this->payment->formattedAmount ?? (($this->payment->amount / 100).' '.strtoupper($this->payment->currency));
        $customer = $this->photoshoot->customer_name ?? __('N/A');

        // Google Calendar link
        $start = $this->startTime->format('Ymd\THis\Z');
        $end = $this->endTime->format('Ymd\THis\Z');
        $calendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            .'&dates='.$start.'%2F'.$end
            .'&details='.urlencode('Photoshoot with '.$customer)
            .'&location='.urlencode($this->photoshoot->location ?? '')
            .'&text='.urlencode($name);

        $mail = (new MailMessage)
            ->subject(__('Booking Confirmed: :name', ['name' => $name]))
            ->greeting(__('Hello!'))
            ->line(__('A new booking has been made.'))
            ->line(__('Session: :name', ['name' => $name]))
            ->line(__('Date: :date', ['date' => $date]))
            ->line(__('Customer: :customer', ['customer' => $customer]))
            ->line(__('Amount: :amount', ['amount' => $amount]))
            ->action(__('Add to Google Calendar'), $calendarUrl);

        if ($this->photoshoot->team->subscribed()) {
            $mail->salutation($this->photoshoot->team->name);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'photoshoot_id' => $this->photoshoot->id,
            'payment_id' => $this->payment->id,
        ];
    }
}
