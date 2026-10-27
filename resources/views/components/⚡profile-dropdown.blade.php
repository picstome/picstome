<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

new class extends Component
{
    public $withName = true;

    public function logout()
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/');
    }
}; ?>

<x-dropdown position="top-start" sm width="sm">
    <x-slot:action>
        <button type="button" x-on:click="show = ! show" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-sage-soft dark:hover:bg-white/10">
            @auth
                <x-avatar :image="auth()->user()->avatar_url" :text="auth()->user()->name" sm />
                @if ($withName)
                    <span class="max-w-40 truncate text-sm font-medium text-brand-ink dark:text-brand-cream">
                        {{ auth()->user()->name }}
                    </span>
                @endif
            @else
                <x-avatar :text="__('Guest')" sm />
                @if ($withName)
                    <span class="text-sm font-medium text-brand-ink dark:text-brand-cream">{{ __('Guest') }}</span>
                @endif
            @endauth

            <x-icon name="chevron-down" class="ml-auto size-3.5 text-zinc-400" />
        </button>
    </x-slot:action>

    @auth
        <x-slot:header>
            <div class="px-2 py-1 text-sm font-normal text-zinc-500">
                {{ __('Account') }}
            </div>
        </x-slot:header>

        <x-dropdown.items :text="__('Profile')" :href="route('settings.profile')" navigate />
        <x-dropdown.items :text="__('Invoice settings')" :href="route('invoices.settings')" navigate />
        <x-dropdown.items :text="__('Branding')" :href="route('branding')" navigate />
        <x-dropdown.items :text="__('Billing')" :href="route('billing-portal')" />
        <x-dropdown.items :text="__('Logout')" wire:click="logout" separator />
    @else
        <x-dropdown.items :text="__('Login')" x-on:click="$tsui.open.modal('login')" />
    @endauth
</x-dropdown>
