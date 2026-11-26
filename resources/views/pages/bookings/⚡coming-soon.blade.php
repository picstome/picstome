<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        if (Auth::user()?->canSeeBookingsFeature()) {
            $this->redirectRoute('bookings', navigate: true);
        }
    }
} ?>

<div class="mx-auto max-w-2xl py-10 sm:py-16">
    <x-ts-card class="text-center">
        <div class="flex flex-col items-center">
            <span class="bg-brand-sage-soft text-brand-green dark:bg-brand-green/15 dark:text-brand-sage flex size-16 items-center justify-center rounded-2xl">
                <x-icon name="calendar-days" class="size-8" />
            </span>

            <x-ts-badge color="green" size="sm" class="mt-5">{{ __('Coming soon') }}</x-ts-badge>

            <x-ts-heading size="xl" level="1" class="text-brand-ink dark:text-brand-cream mt-4 font-semibold">
                {{ __('Bookings are coming soon') }}
            </x-ts-heading>

            <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-2 max-w-lg">
                {{ __('We are putting the finishing touches on online bookings. Soon you will be able to share your availability and accept reservations from your clients.') }}
            </x-ts-text>

            <div class="mt-7 flex flex-wrap items-center justify-center gap-2">
                <x-ts-button :href="route('dashboard')" wire:navigate variant="primary">
                    {{ __('Back to dashboard') }}
                </x-ts-button>
                <x-ts-button :href="route('finance')" wire:navigate variant="secondary">
                    {{ __('Go to Finance') }}
                </x-ts-button>
            </div>
        </div>
    </x-ts-card>
</div>
