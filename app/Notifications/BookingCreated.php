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
        public Payment $payment,
        public string $timezone
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->date->isoFormat('LL');
        $name = $this->photoshoot->name ?? __('Session');
        $amount = $this->payment->formattedAmount;
        $customer = $this->photoshoot->customer_name ?? __('N/A');

        // Google Calendar link
        $calendarUrl = $this->googleCalendarUrl($customer, $name);

        $mail = (new MailMessage)
            ->subject(__('Booking Confirmed: :name', ['name' => $name]))
            ->greeting(__('Hello!'))
            ->line(__('A new booking has been made.'))
            ->line(__('Session: :name', ['name' => $name]))
            ->line(__('Date: :date', ['date' => $date]))
            ->line(__('Start Time: :start', ['start' => $this->startTime->format('g:i A')]))
            ->line(__('End Time: :end', ['end' => $this->endTime->format('g:i A')]))
            ->line(__('Customer: :customer', ['customer' => $customer]))
            ->line(__('Amount: :amount', ['amount' => $amount]))
            ->action(__('Add to Google Calendar'), $calendarUrl);

        if ($this->photoshoot->team->subscribed()) {
            $mail->salutation($this->photoshoot->team->name);
        }

        return $mail;
    }

    private function googleCalendarUrl($customer, $name): string
    {
        $start = $this->startTime->format('Ymd\THis');
        $end = $this->endTime->format('Ymd\THis');

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            .'&dates='.$start.'%2F'.$end
            .'&ctz='.urlencode($this->timezone)
            .'&details='.urlencode(__('Photoshoot with :customer', ['customer' => $customer]))
            .'&location='.urlencode($this->photoshoot->location ?? '')
            .'&text='.urlencode($name);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'photoshoot_id' => $this->photoshoot->id,
            'payment_id' => $this->payment->id,
        ];
    }
}
