<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Passwordless login email requested from the login screen.
 */
class LoginLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your Picstome login link'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Click the button below to log in to your Picstome account.'))
            ->action(__('Log in'), $this->url)
            ->line(__('This link expires in 15 minutes. If you did not request it, you can ignore this email.'))
            ->salutation(__('Best regards, Picstome Team'));
    }
}
