<?php

namespace App\Notifications;

use App\Models\Gallery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SelectionLimitReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Gallery $gallery
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Selection Limit Reached for Gallery: ' . $this->gallery->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your customer has reached the selection limit of ' . $this->gallery->share_selection_limit . ' photos for the gallery "' . $this->gallery->name . '".')
            ->line('You can now start working on the selected photos.')
            ->line('Please note that the customer may have changed pictures in the meantime, so be sure to confirm with the customer before starting.')
            ->action('View Gallery', route('galleries.show', ['gallery' => $this->gallery]))
            ->salutation('Best regards, Picstome Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
