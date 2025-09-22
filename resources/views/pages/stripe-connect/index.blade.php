<?php

use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('stripe.connect');

middleware(['auth', 'verified']);

new class extends Component {
    public $onboardingUrl;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->onboardingUrl = StripeConnectService::createOnboardingLink($team);
    }
} ?>

<x-app-layout>
    @volt('pages.stripe-connect.index')
        <section class="mx-auto max-w-lg">
            <div class="relative mb-6 w-full">
                <flux:heading size="xl" level="1">Stripe Connect</flux:heading>
                <flux:subheading size="lg">
                    Connect your account to Stripe to start accepting payments.
                </flux:subheading>
                <flux:separator variant="subtle" class="mt-6" />
            </div>
            <flux:callout icon="banknotes" variant="secondary">
                <flux:callout.heading>Start accepting payments</flux:callout.heading>
                <flux:callout.text>
                    To begin accepting payments, you need to complete your Stripe onboarding. Click the button below to get started or continue the process.
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button href="{{ $onboardingUrl }}" variant="primary" target="_blank">Begin/Continue Stripe Onboarding</flux:button>
                </x-slot>
            </flux:callout>
        </section>
    @endvolt
</x-app-layout>
