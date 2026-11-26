<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\FormatsEmailRows;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Cashier;

/**
 * Bizum instructions for a payment link. The photographer confirms the
 * transfer later, so this email only carries the reference and the phone.
 */
class PaymentLinkBizumPending extends Notification
{
    use FormatsEmailRows;
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;

        $mail = (new MailMessage)
            ->subject(__('Complete your payment with Bizum: :reference', ['reference' => $payment->reference]))
            ->greeting(__('Hello!'))
            ->line(__('Send the Bizum to complete your payment.'))
            ->line($this->row(__('Description'), $payment->description))
            ->line($this->row(__('Amount'), Cashier::formatAmount($payment->amount, $payment->currency)))
            ->line(__('Send the Bizum to this phone:'))
            ->line('### '.($payment->team->bizum_phone ?: '—'))
            ->line($this->row(__('Reference'), $payment->reference));

        if (filled($payment->team->bizum_instructions)) {
            $mail->line($payment->team->bizum_instructions);
        }

        $mail->line(__('The payment is confirmed as soon as the photographer checks the transfer.'));

        if ($notifiable instanceof AnonymousNotifiable) {
            MailBranding::forTeam($payment->team)->applyTo($mail);
        }

        if ($payment->team->subscribed()) {
            $mail->salutation($payment->team->name);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
        ];
    }
}
