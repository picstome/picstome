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
    <flux:modal name="login" class="w-full sm:max-w-sm">
        <form wire:submit="login" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Login') }}</flux:heading>
                <flux:subheading>{{ __('Welcome Back!') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

            <flux:input
                wire:model="form.password"
                type="password"
                :label="__('Password')"
                :placeholder="__('Your password')"
            />

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Login') }}
            </flux:button>

            <div class="text-center text-sm">
                {{ __('Don\'t have an account?') }}
                <flux:modal.trigger name="register">
                    <button type="button" class="text-zinc-800 dark:text-white underline decoration-zinc-800/20 dark:decoration-white/20">{{ __('Register') }}</button>
                </flux:modal.trigger>
            </div>
        </form>
    </flux:modal>
</div>
