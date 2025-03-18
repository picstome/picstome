<?php

use App\Http\Middleware\SetLocale;
use App\Livewire\Forms\AppearenceSettingsForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);

name('settings.appearance');

new class extends Component
{
    public AppearenceSettingsForm $form;

    public function mount()
    {
        $this->form->setUser(Auth::user());
    }

    public function save()
    {
        $this->form->update();

        return $this->redirectRoute('settings.appearance', navigate: true);
    }
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

                <form wire:submit="save" class="mt-6 space-y-6">
                    <flux:radio.group wire:model="form.language" label="Select your preferred language">
                        <flux:radio value="es" label="Español" />
                        <flux:radio value="en" label="English" />
                    </flux:radio.group>

                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-end">
                            <flux:button variant="primary" type="submit" class="w-full">
                                {{ __('Save') }}
                            </flux:button>
                        </div>

                        <x-action-message class="me-3" on="password-updated">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>
                </form>
            </x-settings.layout>
        </div>
    @endvolt
</x-app-layout>
