<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('login');

middleware('guest');

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
}
?>

<x-guest-layout>
    @volt('pages.login')
        <div class="flex min-h-full items-center">
            <form wire:submit="login" class="mx-auto w-80 max-w-80 space-y-6">
                <div>
                    <div class="mb-12 flex items-center justify-center gap-3">
                        <x-logo-light class="size-5 dark:hidden" />
                        <span class="text-xl font-semibold text-zinc-800 dark:text-white">Picstome</span>
                    </div>
                </div>

                <flux:input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

                <flux:input
                    wire:model="form.password"
                    type="password"
                    :label="__('Password')"
                    :placeholder="__('Your password')"
                />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Log in') }}
                </flux:button>
            </form>
        </div>
    @endvolt
</x-guest-layout>
