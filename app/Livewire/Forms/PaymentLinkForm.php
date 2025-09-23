<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PaymentLinkForm extends Form
{
    #[Validate('required|integer|min:1')]
    public $amount;

    #[Validate('required|string|max:255')]
    public $description;

    public function generatePaymentLink()
    {
        $this->validate();

        return route('handle.pay', [
            'handle' => Auth::user()->currentTeam->handle,
            'amount' => $this->amount,
            'description' => $this->description,
        ]);
    }
}
