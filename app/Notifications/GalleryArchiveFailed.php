<?php

namespace App\Notifications;

use App\Models\GalleryArchive;
use App\Notifications\Concerns\IncludesMagicLoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GalleryArchiveFailed extends Notification implements ShouldQueue
{
    use IncludesMagicLoginLink, Queueable;

    public function __construct(public GalleryArchive $archive) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gallery = $this->archive->gallery;

        $mail = (new MailMessage)
            ->subject(__('The gallery could not be generated: :name', ['name' => $gallery->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The gallery could not be generated. Please contact the administrator.'))
            ->line(__('Gallery: :name', ['name' => $gallery->name]))
            ->action(__('View Gallery'), route('galleries.show', [
                'gallery' => $gallery,
                'activeTab' => 'download',
            ]))
            ->salutation(__('Best regards, Picstome Team'));

        return $this->addMagicLoginLink($mail, $notifiable, route('galleries.show', [
            'gallery' => $gallery,
            'activeTab' => 'download',
        ], absolute: false));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'archive_id' => $this->archive->id,
        ];
    }
}
