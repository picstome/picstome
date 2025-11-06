<?php

use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('dashboard');

middleware(['auth', 'verified']);

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

new class extends Component
{
    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }
} ?>

<x-app-layout>
    @volt('page.dashboard')
        <div>
            <flux:heading size="xl" level="1">
                {{ __('Welcome back, :name', ['name' => $this->user->name]) }}
            </flux:heading>

            <flux:spacer class="my-6" />

            <flux:modal.trigger name="search" shortcut="cmd.k">
                <flux:input as="button" :placeholder="__('Search...')" icon="magnifying-glass" kbd="⌘K" />
            </flux:modal.trigger>

            <flux:spacer class="my-6" />

            @if (! $this->team->subscribed())
                <flux:callout icon="shield-check" color="blue" inline>
                    <flux:callout.heading>{{ __('Get More With Premium') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Unlock 1000GB storage, payments, gallery expiry dates, unlimited contracts, and white label branding. Upgrade to Premium and power up your business.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button href="/pricing">{{ __('Upgrade to Premium') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @endif
        </div>
    @endvolt
</x-app-layout>
