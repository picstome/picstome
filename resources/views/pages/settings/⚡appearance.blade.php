<?php

use App\Livewire\Forms\AppearenceSettingsForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

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

<div class="mx-auto flex max-w-6xl flex-col items-start">
    @include('partials.settings-heading')

    <x-settings.layout
        heading="{{ __('Appearance') }}"
        subheading="{{ __('Update the appearance settings for your account') }}"
    >
        <div class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-white/10">
            <button
                type="button"
                x-on:click="mode = 'light'"
                x-bind:class="mode === 'light' ? 'bg-white text-zinc-800 shadow-xs dark:bg-dark-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
            >
                <x-icon name="sun" class="size-4" />
                {{ __('Light') }}
            </button>

            <button
                type="button"
                x-on:click="mode = 'dark'"
                x-bind:class="mode === 'dark' ? 'bg-white text-zinc-800 shadow-xs dark:bg-dark-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
            >
                <x-icon name="moon" class="size-4" />
                {{ __('Dark') }}
            </button>

            <button
                type="button"
                x-on:click="mode = 'system'"
                x-bind:class="mode === 'system' ? 'bg-white text-zinc-800 shadow-xs dark:bg-dark-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition"
            >
                <x-icon name="computer-desktop" class="size-4" />
                {{ __('System') }}
            </button>
        </div>

        <form wire:submit="save" class="mt-6 space-y-6">
            <x-ts-radio-group :label="__('Select your preferred language')">
                <x-ts-radio wire:model="form.language" name="form.language" value="es" label="Español" />
                <x-ts-radio wire:model="form.language" name="form.language" value="en" label="English" />
            </x-ts-radio-group>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <x-ts-button variant="primary" type="submit" class="w-full">
                        {{ __('Save') }}
                    </x-ts-button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</div>
