<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\FormatsEmailRows;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Cashier;

/**
 * A client chose Bizum on a payment link. The photographer has to check the
 * transfer and confirm it from the payments page.
 */
class PaymentLinkBizumPendingForPhotographer extends Notification
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
            ->subject(__('New Bizum payment pending: :reference', ['reference' => $payment->reference]))
            ->greeting(__('New payment'))
            ->line($this->row(__('Customer'), $payment->customer_name ?: '—'))
            ->line($this->row(__('Email'), $payment->customer_email ?: '—'));

        if (filled($payment->customer_phone)) {
            $mail->line($this->row(__('Phone'), $payment->customer_phone));
        }

        $mail
            ->line($this->row(__('Description'), $payment->description))
            ->line($this->row(__('Amount'), Cashier::formatAmount($payment->amount, $payment->currency)))
            ->line($this->row(__('Reference'), $payment->reference))
            ->line(__('Confirm it from the payments page when the transfer arrives.'))
            ->action(__('Open payments'), route('payments'));

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
        ];
    }
}
