<?php

namespace App\Livewire\Forms;

use App\Livewire\Forms\Concerns\ManagesClientPaymentSettings;
use App\Models\Team;
use Facades\App\Services\StripeConnectService;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PosSettingsForm extends Form
{
    use ManagesClientPaymentSettings;

    public Team $team;

    public $stripe_currency = 'eur';

    public $show_pay_button = true;

    public $stripe_test_mode = false;

    public function rules(): array
    {
        $currencies = array_map('strtolower', StripeConnectService::supportedCurrencies());

        return [
            ...$this->clientPaymentRules(),
            'stripe_currency' => ['required', 'string', 'size:3', Rule::in($currencies)],
            'show_pay_button' => ['boolean'],
            'stripe_test_mode' => ['boolean'],
        ];
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;

        $this->stripe_currency = $team->stripe_currency ?? 'eur';
        $this->show_pay_button = $team->show_pay_button ?? true;
        $this->stripe_test_mode = $team->stripe_test_mode ?? false;

        $this->fillClientPayment($team);
    }

    public function update(): void
    {
        $this->validate();

        $this->team->update([
            'stripe_currency' => $this->stripe_currency,
            'show_pay_button' => $this->show_pay_button,
            'stripe_test_mode' => $this->stripe_test_mode,
        ]);

        $this->updateTeamClientPayment($this->team);
    }
}
