<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Notifications\Concerns\IncludesMagicLoginLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the signed-contract PDF could not be generated after all retries.
 *
 * The contract is already executed, so the mail points back to the contract
 * where the photographer can request the PDF again.
 */
class ContractPdfFailed extends Notification implements ShouldQueue
{
    use IncludesMagicLoginLink, Queueable;

    public function __construct(public Contract $contract) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('The contract PDF could not be generated: :name', ['name' => $this->contract->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We could not generate the PDF for contract :name. Open the contract and request it again.', ['name' => $this->contract->title]))
            ->action(__('View contract'), route('contracts.show', ['contract' => $this->contract]))
            ->salutation(__('Best regards, Picstome Team'));

        return $this->addMagicLoginLink($mail, $notifiable, route('contracts.show', [
            'contract' => $this->contract,
        ], absolute: false));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'contract_id' => $this->contract->id,
        ];
    }
}
