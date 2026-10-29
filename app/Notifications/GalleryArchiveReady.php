<?php

namespace App\Notifications;

use App\Models\GalleryArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GalleryArchiveReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GalleryArchive $archive) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gallery = $this->archive->gallery;

        return (new MailMessage)
            ->subject(__('Your gallery ZIP is ready: :name', ['name' => $gallery->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The ZIP archive for the gallery ":name" is ready to download.', ['name' => $gallery->name]))
            ->line(__('You can notify your client that the photos are ready to download.'))
            ->line(__('The ZIP will be available for :days days.', ['days' => (int) config('picstome.gallery_archives.ttl_days', 7)]))
            ->action(__('Open Download tab'), route('galleries.show', [
                'gallery' => $gallery,
                'activeTab' => 'download',
            ]))
            ->salutation(__('Best regards, Picstome Team'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'archive_id' => $this->archive->id,
        ];
    }
}
