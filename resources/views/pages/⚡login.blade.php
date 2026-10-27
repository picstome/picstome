<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login()
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: '/');
    }
} ?>

<div class="flex min-h-full items-center">
    <form wire:submit="login" class="mx-auto w-80 max-w-80 space-y-6">
        <div class="mb-10 flex justify-center">
            <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome">
            <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome">
        </div>

        @if(session('status'))
            <x-ts-text variant="strong" class="text-center font-medium">{{ session('status') }}</x-ts-text>
        @endif

        <x-ts-input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

        <div>
            <x-ts-input
                wire:model="form.password"
                type="password"
                :label="__('Password')"
                :placeholder="__('Your password')"
            />

            <x-ts-text class="mt-2">
                <x-ts-link variant="subtle" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </x-ts-link>
            </x-ts-text>
        </div>

        <x-ts-button type="submit" variant="primary" class="w-full">
            {{ __('Log in') }}
        </x-ts-button>

        <x-ts-text class="text-center">
            {{ __('Don\'t have an account?') }}
            <x-ts-link href="{{ route('register') }}" wire:navigate>
                {{ __('Register') }}
            </x-ts-link>
        </x-ts-text>
    </form>
</div>
