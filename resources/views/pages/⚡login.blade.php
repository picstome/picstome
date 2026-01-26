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
            <flux:text variant="strong" class="text-center font-medium">{{ session('status') }}</flux:text>
        @endif

        <flux:input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

        <div>
            <flux:input
                wire:model="form.password"
                type="password"
                :label="__('Password')"
                :placeholder="__('Your password')"
            />

            <flux:text class="mt-2">
                <flux:link variant="subtle" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            </flux:text>
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            {{ __('Log in') }}
        </flux:button>

        <flux:text class="text-center">
            {{ __('Don\'t have an account?') }}
            <flux:link href="{{ route('register') }}" wire:navigate>
                {{ __('Register') }}
            </flux:link>
        </flux:text>
    </form>
</div>
