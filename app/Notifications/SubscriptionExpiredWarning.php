<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredWarning extends Notification
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
                    ->line(__('Your subscription has expired.'))
                    ->action(__('Renew Subscription'), route('billing-portal'))
                    ->line(__('Your data will be deleted in :days days if not renewed.', ['days' => $this->daysLeft]));
    }
}
