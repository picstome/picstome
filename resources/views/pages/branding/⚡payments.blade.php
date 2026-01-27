<?php
use App\Livewire\Forms\PosSettingsForm;
use Facades\App\Services\StripeConnectService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

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
        $team = Auth::user()->currentTeam;

        if ($team->stripe_test_mode) {
            $team->update([
                'stripe_test_account_id' => null,
                'stripe_test_onboarded' => false,
            ]);
        } else {
            $team->update([
                'stripe_account_id' => null,
                'stripe_onboarded' => false,
            ]);
        }

        $this->redirect(route('branding.general'), navigate: true);
    }
}; ?>

<section class="mx-auto max-w-6xl">
    @include('partials.branding-header')

    <div class="flex items-start max-md:flex-col">
        <div class="mr-10 w-full pb-4 md:w-[220px]">
            @include('partials.branding-nav')
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <flux:heading>{{ __('Payment Settings') }}</flux:heading>
            <flux:subheading>
                {{ __('Configure your payment settings, including default currency for payments.') }}
            </flux:subheading>

            @if (! (Auth::user()->currentTeam?->subscribed() ?? false))
                <flux:callout icon="credit-card" variant="secondary" class="mt-5 max-w-prose">
                    <flux:callout.heading>{{ __('Subscribe to enable payments') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('The payments feature is available for subscribed users only. Please subscribe to unlock payment links and payment management.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @else
                <div class="mt-5 w-full max-w-lg">
                    <form wire:submit="save" class="space-y-6">
                        <flux:field>
                            <flux:checkbox
                                wire:model="form.stripe_test_mode"
                                :label="__('Enable Stripe Test Mode')"
                                :description="__('When enabled, all payments and your connected account will be in Stripe test mode. No real money is transferred.')"
                            />
                        </flux:field>
                        <flux:separator class="my-8" />
                        <flux:field>
                            <flux:label>{{ __('Currency') }}</flux:label>
                            <flux:select wire:model="form.stripe_currency">
                                @foreach ($stripeCurrencies as $currency)
                                    <option value="{{ strtolower($currency) }}">
                                        {{ strtoupper($currency) }}
                                    </option>
                                @endforeach
                            </flux:select>
                            <flux:description>
                                {{ __('Select currency used for payments.') }}
                            </flux:description>
                            <flux:error name="form.stripe_currency" />
                        </flux:field>
                        <flux:field>
                            <flux:checkbox
                                wire:model="form.show_pay_button"
                                :label="__('Show Accept Payments Button')"
                                :description="__('Enable or disable accept payments button on your public profile.')"
                            />
                        </flux:field>
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </form>

                    @if (Auth::user()->currentTeam->hasCompletedOnboarding())
                        <flux:separator class="my-8" />

                        <div>
                            <flux:button
                                wire:click="disconnectStripe"
                                wire:confirm="{{ __('Are you sure you want to disconnect your Stripe account?') }}"
                            >
                                {{ __('Disconnect Stripe') }}
                            </flux:button>

                            <flux:description class="mt-2">
                                {{ __('Disconnecting will remove your Stripe account for current mode (test or live). You can reconnect at any time.') }}
                            </flux:description>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
