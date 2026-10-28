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
        <x-ts-heading size="xl" level="1">{{ __("Stripe Connect") }}</x-ts-heading>
        <x-ts-subheading size="lg">
            {{ __("Connect your account to Stripe to start accepting payments.") }}
        </x-ts-subheading>
        <x-ts-separator variant="subtle" class="mt-6" />
    </div>
    @if (FakePaymentService::enabled())
        <x-ts-callout icon="beaker" variant="secondary" class="mt-6">
            <x-ts-callout-heading>{{ __('Fake payments mode is enabled') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('Payments are created without Stripe, so you can test the whole flow without a connected account. Turn it off in your .env file (PAYMENTS_FAKE=false) to use Stripe for real.') }}
            </x-ts-callout-text>
        </x-ts-callout>
    @endif

    @if ($this->onboardingComplete)
        <x-ts-callout icon="check-circle" variant="secondary" class="mt-6">
            <x-ts-callout-heading>{{ __('Stripe is connected') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('Your account is ready to accept payments.') }}
            </x-ts-callout-text>
        </x-ts-callout>
    @endif

    @if ($this->accountEmail && $this->accountEmail !== Auth::user()->currentTeam->stripeEmail())
        <x-ts-callout icon="exclamation-triangle" variant="secondary" class="mt-6">
            <x-ts-callout-heading>{{ __('Stripe is still using an old email') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('Stripe sends the onboarding emails to :email. Update it in your Stripe dashboard to receive them in your current inbox.', ['email' => $this->accountEmail]) }}
            </x-ts-callout-text>
        </x-ts-callout>
    @endif

    <x-ts-callout icon="banknotes" variant="secondary">
        <x-ts-callout-heading>{{ __("Start accepting payments") }}</x-ts-callout-heading>
        <x-ts-callout-text>
            {{ __("To begin accepting payments, you need to complete your Stripe onboarding. Click the button below to get started or continue the process.") }}
        </x-ts-callout-text>
        <x-slot name="actions">
            <x-ts-button href="{{ $onboardingUrl }}" variant="primary" target="_blank">{{ __("Begin Stripe Onboarding") }}</x-ts-button>
        </x-slot>
    </x-ts-callout>
</section>
