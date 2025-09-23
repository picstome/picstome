<?php

namespace App\Livewire\Forms;

use App\Models\Payment;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PaymentForm extends Form
{
    #[Validate('required|integer|min:1')]
    public $amount;

    #[Validate('required|string|max:10')]
    public $currency = 'usd';

    #[Validate('required|string|max:255')]
    public $description;

    public ?Payment $payment = null;

    public function setPayment(Payment $payment)
    {
        $this->payment = $payment;
        $this->amount = $payment->amount;
        $this->currency = $payment->currency;
        $this->description = $payment->description;
    }

    public function update()
    {
        $this->validate();

        $this->payment->update([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
        ]);
    }
}
