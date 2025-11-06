<?php

use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('dashboard');

middleware(['auth', 'verified']);

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

new class extends Component
{
    #[Computed]
    public function currentTeam()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }
} ?>
<x-app-layout>
    @volt('page.dashboard')
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => $this->user->name]) }}</flux:heading>

            <flux:modal.trigger name="search" shortcut="cmd.k">
                <flux:input
                    as="button"
                    :placeholder="__('Search...')"
                    icon="magnifying-glass"
                    kbd="⌘K"
                />
            </flux:modal.trigger>
        </div>
    @endvolt
</x-app-layout>
