<?php

namespace App\Notifications;

use App\Traits\FormatsFileSize;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StorageAlmostFull extends Notification implements ShouldQueue
{
    use FormatsFileSize, Queueable;

    public function __construct(
        public int $used,
        public int $limit,
        public string $upgradeUrl,
    ) {}

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
            ->subject(__('Your storage is almost full'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You have used :used of :total storage.', [
                'used' => $this->formatFileSize($this->used),
                'total' => $this->formatFileSize($this->limit),
            ]))
            ->line(__('Choose a plan with more storage to keep uploading photos and videos.'))
            ->action(__('See plans'), $this->upgradeUrl)
            ->salutation(__('Best regards, Picstome Team'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'used' => $this->used,
            'limit' => $this->limit,
        ];
    }
}
