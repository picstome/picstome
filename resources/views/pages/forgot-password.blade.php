<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('password.request');

middleware('guest');

new class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}
?>

<x-guest-layout>
    @volt('pages.forgot-password')
        <div class="flex min-h-full items-center">
            <form wire:submit="sendPasswordResetLink" class="mx-auto w-80 max-w-80 space-y-6">
                <div class="mb-10 flex justify-center">
                    <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome">
                    <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome">
                </div>

                @if(session('status'))
                    <flux:text variant="strong" class="text-center font-medium">{{ session('status') }}</flux:text>
                @endif

                <flux:input
                    wire:model="email"
                    :label="__('Email Address')"
                    type="email"
                    required
                    autofocus
                    placeholder="email@example.com"
                />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Email password reset link') }}
                </flux:button>

                <flux:text class="space-x-1 text-center">
                    <span>{{ __('Or, return to') }}</span>
                    <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
                </flux:text>
            </form>
        </div>
    @endvolt
</x-guest-layout>
