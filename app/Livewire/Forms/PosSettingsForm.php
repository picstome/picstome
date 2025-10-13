<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Facades\App\Services\StripeConnectService;
use Livewire\Form;
use Illuminate\Validation\Rule;

class PosSettingsForm extends Form
{
    public Team $team;

    public $stripe_currency = 'eur';
    public $show_pay_button = true;

    public function rules()
    {
        $currencies = array_map('strtolower', StripeConnectService::supportedCurrencies());

        return [
            'stripe_currency' => ['required', 'string', 'size:3', Rule::in($currencies)],
            'show_pay_button' => ['boolean'],
        ];
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->stripe_currency = $team->stripe_currency ?? 'eur';
        $this->show_pay_button = $team->show_pay_button ?? true;
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'stripe_currency' => $this->stripe_currency,
            'show_pay_button' => $this->show_pay_button,
        ]);
    }
}
