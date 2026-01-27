<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(
        public int $daysLeft
    ) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line(__('Your subscription expires in :days days.', ['days' => $this->daysLeft]))
            ->action(__('Renew Subscription'), route('billing-portal'))
            ->line(__('Thank you for using our application!'));
    }
}
