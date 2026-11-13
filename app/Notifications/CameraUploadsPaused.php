<?php

namespace App\Notifications;

use App\Models\FtpAccount;
use App\Traits\FormatsFileSize;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Camera uploads stop when the team runs out of storage.
 *
 * Without this email the camera keeps transferring while every shot is
 * discarded, and the photographer only finds out when the gallery stays empty.
 * Some tethering setups delete the card after a successful upload, so saying
 * how many files were dropped matters.
 */
class CameraUploadsPaused extends Notification implements ShouldQueue
{
    use FormatsFileSize, Queueable;

    public function __construct(
        public FtpAccount $account,
        public int $discardedFiles,
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
        $team = $this->account->team;
        $used = $team->calculateStorageUsed();
        $limit = $team->storage_limit;

        $mail = (new MailMessage)
            ->subject(__('Camera uploads paused: no storage left'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your studio has used all of its storage, so photos sent from the camera are being discarded.'))
            ->line(__('Used: :used of :total.', [
                'used' => $this->formatFileSize($used),
                'total' => $limit === null ? __('Unlimited') : $this->formatFileSize($limit),
            ]))
            ->line(trans_choice(
                '{1} :count photo was discarded before uploads were paused.|[2,*] :count photos were discarded before uploads were paused.',
                $this->discardedFiles,
                ['count' => $this->discardedFiles],
            ))
            ->line(__('Free up space or upgrade your plan to resume camera uploads.'))
            ->action(__('See plans'), $team->storageUpgradeUrl());

        return $mail->salutation(__('Best regards, Picstome Team'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ftp_account_id' => $this->account->id,
            'discarded_files' => $this->discardedFiles,
        ];
    }
}
