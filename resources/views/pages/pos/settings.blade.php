<?php
use App\Livewire\Forms\PosSettingsForm;
use Facades\App\Services\StripeConnectService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('pos.settings');

middleware(['auth', 'verified']);

new class extends Component {
    public PosSettingsForm $form;

    public $stripeCurrencies = [];

    public function mount() {
        $this->form->setTeam(Auth::user()->currentTeam);

        $this->stripeCurrencies = StripeConnectService::supportedCurrencies();
    }

    public function save() {
        $this->form->update();

        Flux::toast(__('Your changes have been saved.'), variant: 'success');
    }
}; ?>

<x-app-layout>
    @volt('pages.pos.settings')
        <section class="mx-auto max-w-lg">
            <div class="relative mb-6 w-full">
                <flux:heading size="xl" level="1">{{ __('POS Settings') }}</flux:heading>
                <flux:subheading size="lg">
                    {{ __('Configure your point-of-sale settings, including currency for payments.') }}
                </flux:subheading>
                <flux:separator variant="subtle" class="mt-6" />
            </div>
            <div class="mt-5 space-y-12">
                <form wire:submit="save" class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Currency') }}</flux:label>
                        <flux:select wire:model="form.stripe_currency">
                            @foreach($stripeCurrencies as $currency)
                                <option value="{{ strtolower($currency) }}">{{ strtoupper($currency) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:description>
                            {{ __('Select the currency used for payments in your POS.') }}
                        </flux:description>
                        <flux:error name="form.stripe_currency" />
                    </flux:field>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
            </div>
        </section>
    @endvolt
</x-app-layout>
