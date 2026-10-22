<?php
use App\Services\FakePaymentService;
use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public $onboardingUrl;

    public ?string $accountEmail = null;

    public bool $onboardingComplete = false;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->onboardingComplete = StripeConnectService::syncOnboardingStatus($team);
        $this->onboardingUrl = StripeConnectService::createOnboardingLink($team);
        $this->accountEmail = StripeConnectService::accountEmail($team);
    }
} ?>

<section class="mx-auto max-w-lg">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __("Stripe Connect") }}</flux:heading>
        <flux:subheading size="lg">
            {{ __("Connect your account to Stripe to start accepting payments.") }}
        </flux:subheading>
        <flux:separator variant="subtle" class="mt-6" />
    </div>
    @if (FakePaymentService::enabled())
        <flux:callout icon="beaker" variant="secondary" class="mt-6">
            <flux:callout.heading>{{ __('Fake payments mode is enabled') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Payments are created without Stripe, so you can test the whole flow without a connected account. Turn it off in your .env file (PAYMENTS_FAKE=false) to use Stripe for real.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->onboardingComplete)
        <flux:callout icon="check-circle" variant="secondary" class="mt-6">
            <flux:callout.heading>{{ __('Stripe is connected') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Your account is ready to accept payments.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->accountEmail && $this->accountEmail !== Auth::user()->currentTeam->stripeEmail())
        <flux:callout icon="exclamation-triangle" variant="secondary" class="mt-6">
            <flux:callout.heading>{{ __('Stripe is still using an old email') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Stripe sends the onboarding emails to :email. Update it in your Stripe dashboard to receive them in your current inbox.', ['email' => $this->accountEmail]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:callout icon="banknotes" variant="secondary">
        <flux:callout.heading>{{ __("Start accepting payments") }}</flux:callout.heading>
        <flux:callout.text>
            {{ __("To begin accepting payments, you need to complete your Stripe onboarding. Click the button below to get started or continue the process.") }}
        </flux:callout.text>
        <x-slot name="actions">
            <flux:button href="{{ $onboardingUrl }}" variant="primary" target="_blank">{{ __("Begin Stripe Onboarding") }}</flux:button>
        </x-slot>
    </flux:callout>
</section>
