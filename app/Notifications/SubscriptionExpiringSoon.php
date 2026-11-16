<?php

namespace App\Notifications;

use App\Notifications\Concerns\IncludesMagicLoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification
{
    use IncludesMagicLoginLink, Queueable;

    public function __construct(
        public int $daysLeft
    ) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject(__('Your subscription expires soon'))
            ->line(__('Your subscription expires in :days days.', ['days' => $this->daysLeft]))
            ->action(__('Renew Subscription'), route('billing-portal'))
            ->line(__('Thank you for using our application!'));

        return $this->addMagicLoginLink($mail, $notifiable, route('payments', absolute: false));
    }
}
