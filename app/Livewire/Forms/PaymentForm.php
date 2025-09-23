<?php

namespace App\Livewire\Forms;

use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PaymentForm extends Form
{
    #[Validate('required|numeric|min:0.01')]
    public $amount;

    #[Validate('required|string|max:10')]
    public $currency = 'usd';

    #[Validate('required|string|max:255')]
    public $description;

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->payments()->create([
            'amount' => (int) round($this->amount * 100), // store as cents
            'currency' => $this->currency,
            'description' => $this->description,
        ]);
    }
}
