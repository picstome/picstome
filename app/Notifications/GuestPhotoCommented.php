<?php

namespace App\Notifications;

use App\Models\Photo;
use App\Models\PhotoComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestPhotoCommented extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Photo $photo, public PhotoComment $comment) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New guest comment on your photo'))
            ->greeting(__('Hello!'))
            ->line(__('A guest has left a new comment on a photo in your gallery:'))
            ->line(__('Gallery: :name', ['name' => $this->photo->gallery->name]))
            ->line(__('Photo: :name', ['name' => $this->photo->name]))
            ->line(__('Comment: ":comment"', ['comment' => $this->comment->comment]))
            ->action(__('View Photo'), url('/galleries/'.$this->photo->gallery->id.'/photos/'.$this->photo->id))
            ->line(__('You are receiving this because a guest commented on your shared gallery.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
