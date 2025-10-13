<?php
use App\Livewire\Forms\PosSettingsForm;
use Facades\App\Services\StripeConnectService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('branding.pos');

middleware(['auth', 'verified']);

new class extends Component
{
    public PosSettingsForm $form;

    public $stripeCurrencies = [];

    public function mount()
    {
        $this->form->setTeam(Auth::user()->currentTeam);

        $this->stripeCurrencies = StripeConnectService::supportedCurrencies();
    }

    public function save()
    {
        $this->form->update();

        Flux::toast(__('Your changes have been saved.'), variant: 'success');
    }

    public function disconnectStripe()
    {
        Auth::user()->currentTeam->update([
            'stripe_account_id' => null,
            'stripe_onboarded' => false,
        ]);

        $this->redirect(route('branding.general'), navigate: true);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.pos')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('POS Settings') }}</flux:heading>
                    <flux:subheading>{{ __('Configure your point-of-sale settings, including currency for payments.') }}</flux:subheading>

                    <div class="mt-5 w-full max-w-lg">
                        <form wire:submit="save" class="space-y-6">
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
                            <flux:field>
                                <flux:checkbox wire:model="form.show_pay_button" :label="__('Show Accept Payments Button')" :description="__('Enable or disable the accept payments button on your public profile.')" />
                            </flux:field>
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>

                        <flux:separator class="my-8" />

                        <div>
                            <flux:button wire:click="disconnectStripe" wire:confirm="{{ __('Are you sure you want to disconnect your Stripe account?') }}">
                                {{ __('Disconnect Stripe') }}
                            </flux:button>

                            <flux:description class="mt-2">
                                {{ __('Disconnecting will remove your Stripe account. You can reconnect at any time.') }}
                            </flux:description>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>
