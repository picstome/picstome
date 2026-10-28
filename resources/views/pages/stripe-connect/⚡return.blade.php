<?php
use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public $onboardingUrl = null;

    public $onboardingComplete = false;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->onboardingComplete = StripeConnectService::syncOnboardingStatus($team);

        if (! $this->onboardingComplete) {
            $this->onboardingUrl = StripeConnectService::createOnboardingLink($team);
        }
    }
} ?>

<section class="mx-auto max-w-lg mt-10">
    <div class="relative mb-6 w-full">
        <x-ts-heading size="xl" level="1">Stripe Connect</x-ts-heading>
        <x-ts-subheading size="lg">
            Check your Stripe onboarding status and start accepting payments.
        </x-ts-subheading>
        <x-ts-separator variant="subtle" class="mt-6" />
    </div>

    @if ($onboardingComplete)
        <x-ts-callout variant="secondary" icon="check-circle">
            <x-ts-callout-heading>Stripe onboarding complete!</x-ts-callout-heading>
            <x-ts-callout-text>
                Your Stripe account is now connected. You can start accepting payments.
            </x-ts-callout-text>
        </x-ts-callout>
    @else
        <x-ts-callout variant="secondary" icon="exclamation-circle">
            <x-ts-callout-heading>Stripe onboarding not complete</x-ts-callout-heading>
            <x-ts-callout-text>
                You have not finished connecting your Stripe account. Please complete onboarding to accept payments.
            </x-ts-callout-text>
            <x-slot name="actions">
                <x-ts-button href="{{ $onboardingUrl }}" variant="primary" target="_blank">Continue Stripe Onboarding</x-ts-button>
            </x-slot>
        </x-ts-callout>
    @endif
</section>
