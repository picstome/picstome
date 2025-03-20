<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

use function Laravel\Folio\name;

name('login');

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
        </form>
    </flux:modal>
</div>
