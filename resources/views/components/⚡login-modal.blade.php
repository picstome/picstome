<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component
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

<div>
    <x-ts-modal name="login" class="w-full sm:max-w-sm">
        <form wire:submit="login" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Login') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Welcome Back!') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

            <x-ts-input
                wire:model="form.password"
                type="password"
                :label="__('Password')"
                :placeholder="__('Your password')"
            />

            <x-ts-button type="submit" variant="primary" class="w-full">
                {{ __('Login') }}
            </x-ts-button>

            <div class="text-center text-sm">
                {{ __('Don\'t have an account?') }}
                <x-ts-modal-trigger name="register">
                    <button type="button" class="text-zinc-800 dark:text-white underline decoration-zinc-800/20 dark:decoration-white/20">{{ __('Register') }}</button>
                </x-ts-modal-trigger>
            </div>
        </form>
    </x-ts-modal>
</div>
