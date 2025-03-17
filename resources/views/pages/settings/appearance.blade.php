<?php

use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);

name('settings.appearance');

new class extends Component
{
    //
}; ?>

<x-app-layout>
    @volt('pages.settings.appearance')
        <div class="mx-auto flex max-w-6xl flex-col items-start">
            @include('partials.settings-heading')

            <x-settings.layout heading="{{ __('Appearance') }}" subheading="{{ __('Update the appearance settings for your account') }}">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">
                        {{ __('System') }}
                    </flux:radio>
                </flux:radio.group>
            </x-settings.layout>
        </div>
    @endvolt
</x-app-layout>
