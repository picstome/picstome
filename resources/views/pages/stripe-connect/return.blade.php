<?php

use Facades\App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;

use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('stripe.connect.return');

new class extends Component {
    public $onboardingUrl;
    public $onboardingComplete;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->onboardingComplete = StripeConnectService::isOnboardingComplete($team);

        $this->onboardingUrl = StripeConnectService::createOnboardingLink($team);
    }
} ?>

<x-app-layout>
    @volt('pages.stripe-connect.return')
        <section class="mx-auto max-w-lg mt-10">
            @if ($onboardingComplete)
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>Stripe onboarding complete!</flux:callout.heading>
                    <flux:callout.text>
                        Your Stripe account is now connected. You can start accepting payments.
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:callout variant="warning" icon="exclamation-circle">
                    <flux:callout.heading>Stripe onboarding not complete</flux:callout.heading>
                    <flux:callout.text>
                        You have not finished connecting your Stripe account. Please complete onboarding to accept payments.
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button href="{{ $onboardingUrl }}" variant="primary" target="_blank">Continue Stripe Onboarding</flux:button>
                    </x-slot>
                </flux:callout>
            @endif
        </section>
    @endvolt
</x-app-layout>
