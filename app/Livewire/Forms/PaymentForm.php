<?php

namespace App\Livewire\Forms;

use App\Models\Payment;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PaymentForm extends Form
{
    public ?Payment $payment = null;

    public $photoshoot_id;

    protected function rules()
    {
        return [
            'photoshoot_id' => [
                'nullable',
                Rule::exists('photoshoots', 'id')
                    ->where(fn ($query) => $query->where('team_id', $this->payment?->team_id)),
            ],
        ];
    }

    public function setPayment(Payment $payment)
    {
        $this->payment = $payment;
        $this->photoshoot_id = $payment->photoshoot_id;
    }

    public function update()
    {
        $this->validate();

        $this->payment->update([
            'photoshoot_id' => empty($this->photoshoot_id) ? null : $this->photoshoot_id,
        ]);
    }
}
