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
            ->subject(__('Selection Limit Reached for Gallery: ') . $this->gallery->name)
            ->greeting(__('Hello ') . $notifiable->name . ',')
            ->line(__('Your customer has reached the selection limit of ') . $this->gallery->share_selection_limit . __(' photos for the gallery "') . $this->gallery->name . __('".'))
            ->line(__('You can now start working on the selected photos.'))
            ->line(__('Please note that the customer may have changed pictures in the meantime, so be sure to confirm with the customer before starting.'))
            ->action(__('View Gallery'), route('galleries.show', ['gallery' => $this->gallery]))
            ->salutation(__('Best regards, Picstome Team'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
