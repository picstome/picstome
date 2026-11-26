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
 * The photographer confirmed the Bizum transfer of a payment link.
 */
class PaymentLinkBizumConfirmed extends Notification
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
            ->subject(__('Payment confirmed: :reference', ['reference' => $payment->reference]))
            ->greeting(__('Hello!'))
            ->line(__('We have received your payment.'))
            ->line($this->row(__('Description'), $payment->description))
            ->line($this->row(__('Amount'), Cashier::formatAmount($payment->amount, $payment->currency)))
            ->line($this->row(__('Reference'), $payment->reference))
            ->line(__('Thank you!'));

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
