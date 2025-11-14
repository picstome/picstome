<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestPhotoCommented extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public $photo;

    public $comment;

    public function __construct($photo, $comment)
    {
        $this->photo = $photo;
        $this->comment = $comment;
    }

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
            ->subject('New guest comment on your photo')
            ->greeting('Hello!')
            ->line('A guest has left a new comment on a photo in your gallery:')
            ->line('Gallery: '.$this->photo->gallery->name)
            ->line('Photo: '.$this->photo->name)
            ->line('Comment: "'.$this->comment->comment.'"')
            ->action('View Photo', url('/shares/'.$this->photo->gallery->ulid.'/photos/'.$this->photo->id))
            ->line('You are receiving this because a guest commented on your shared gallery.');
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
