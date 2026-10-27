<?php
use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\PosSettingsForm;
use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    use Interactions;
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

        $this->toast()->success(__('Your changes have been saved.'))->send();
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

        <x-ts-separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <x-ts-heading>{{ __('Payment Settings') }}</x-ts-heading>
            <x-ts-subheading>
                {{ __('Configure your payment settings, including default currency for payments.') }}
            </x-ts-subheading>

            @if (! (Auth::user()->currentTeam?->subscribed() ?? false))
                <x-ts-callout icon="credit-card" variant="secondary" class="mt-5 max-w-prose">
                    <x-ts-callout-heading>{{ __('Subscribe to enable payments') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('The payments feature is available for subscribed users only. Please subscribe to unlock payment links and payment management.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @else
                <div class="mt-5 w-full max-w-lg">
                    <form wire:submit="save" class="space-y-6">
                        <x-ts-field>
                            <x-ts-checkbox
                                wire:model="form.stripe_test_mode"
                                :label="__('Enable Stripe Test Mode')"
                                :description="__('When enabled, all payments and your connected account will be in Stripe test mode. No real money is transferred.')"
                            />
                        </x-ts-field>
                        <x-ts-separator class="my-8" />
                        <x-ts-field>
                            <x-ts-label>{{ __('Currency') }}</x-ts-label>
                            <x-ts-select wire:model="form.stripe_currency">
                                @foreach ($stripeCurrencies as $currency)
                                    <option value="{{ strtolower($currency) }}">
                                        {{ strtoupper($currency) }}
                                    </option>
                                @endforeach
                            </x-ts-select>
                            <x-ts-description>
                                {{ __('Select currency used for payments.') }}
                            </x-ts-description>

                        </x-ts-field>
                        <x-ts-field>
                            <x-ts-checkbox
                                wire:model="form.show_pay_button"
                                :label="__('Show Accept Payments Button')"
                                :description="__('Enable or disable accept payments button on your public profile.')"
                            />
                        </x-ts-field>
                        <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                    </form>

                    @if (Auth::user()->currentTeam->hasCompletedOnboarding())
                        <x-ts-separator class="my-8" />

                        <div>
                            <x-ts-modal-trigger name="disconnect-stripe">
                                <x-ts-button>
                                    {{ __('Disconnect Stripe') }}
                                </x-ts-button>
                            </x-ts-modal-trigger>

                            <x-ts-description class="mt-2">
                                {{ __('Disconnecting will remove your Stripe account for current mode (test or live). You can reconnect at any time.') }}
                            </x-ts-description>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <x-confirm-modal
        name="disconnect-stripe"
        :title="__('Disconnect Stripe?')"
        :message="__('Are you sure you want to disconnect your Stripe account?')"
        :confirm-label="__('Disconnect Stripe')"
        method="disconnectStripe"
    />
</section>
