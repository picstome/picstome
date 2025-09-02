<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredWarning extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $daysLeft = 6; // 7 day grace period - 1 day after expiration

        return (new MailMessage)
                    ->line(__('Your subscription has expired.'))
                    ->action(__('Renew Subscription'), route('billing-portal'))
                    ->line(__('Your data will be deleted in :days days if not renewed.', ['days' => $daysLeft]));
    }
}
