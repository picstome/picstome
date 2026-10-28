<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
} ?>

<div class="flex min-h-full items-center">
    <form wire:submit="sendPasswordResetLink" class="mx-auto w-80 max-w-80 space-y-6">
        <div class="mb-10 flex justify-center">
            <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome">
            <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome">
        </div>

        @if(session('status'))
            <x-ts-text variant="strong" class="text-center font-medium">{{ session('status') }}</x-ts-text>
        @endif

        <x-ts-input
            wire:model="email"
            :label="__('Email Address')"
            type="email"
            required
            autofocus
            placeholder="email@example.com"
        />

        <x-ts-button type="submit" variant="primary" class="w-full">
            {{ __('Email password reset link') }}
        </x-ts-button>

        <x-ts-text class="space-x-1 text-center">
            <span>{{ __('Or, return to') }}</span>
            <x-ts-link :href="route('login')" wire:navigate>{{ __('log in') }}</x-ts-link>
        </x-ts-text>
    </form>
</div>
