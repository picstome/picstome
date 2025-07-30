<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

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

<flux:dropdown position="top" align="start">
    @auth
        <flux:profile :name="$withName ? auth()->user()->name : false" :avatar="auth()->user()->avatar_url" />

        <flux:menu>
            <flux:menu.group :heading="__('Account')">
                <flux:menu.item :href="route('settings.profile')">{{ __('Profile') }}</flux:menu.item>
                <flux:menu.item :href="route('billing-portal')">{{ __('Billing') }}</flux:menu.item>
            </flux:menu.group>
            <flux:menu.item wire:click="logout" variant="danger">{{ __('Logout') }}</flux:menu.item>
        </flux:menu>
    @else
        <flux:profile :name="__('Guest')" />

        <flux:menu>
            <flux:modal.trigger name="login">
                <flux:menu.item>{{ __('Login') }}</flux:menu.item>
            </flux:modal.trigger>
        </flux:menu>
    @endauth
</flux:dropdown>
