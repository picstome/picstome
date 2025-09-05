<?php

use App\Livewire\Forms\BrandingForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding.general');

middleware('auth');

render(function (View $view) {
    return $view->with('team', Team::first());
});

new class extends Component
{
    use WithFileUploads;

    public Team $team;

    public BrandingForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->redirectRoute('branding.general');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.general')
        <div>
            @include('partials.branding-header')

            <div class="flex">
                @include('partials.branding-nav')

            <!-- Main Content -->
            <div class="flex-1 p-6">
                <div class="mx-auto max-w-xl">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="max-sm:w-full sm:flex-1">
                            <x-heading level="1" size="xl">{{ __('General Settings') }}</x-heading>
                            <x-subheading>{{ __('Basic branding information for your studio.') }}</x-subheading>
                        </div>
                    </div>

                    <flux:separator class="my-10 mt-6" />

                    <form wire:submit="save" class="space-y-6">
                        <flux:input wire:model="form.name" :label="__('Studio name')" />

                        <flux:field>
                            <flux:input wire:model="form.handle" :label="__('Username')" :placeholder="__('e.g. mystudio')" />
                            <flux:description>
                                {{ __('This username is used for your public profile.') }}
                                <flux:link :href="route('handle.show', ['handle' => $team->handle])" target="_blank">
                                    {{ __('View your public profile') }}
                                </flux:link>.
                            </flux:description>
                        </flux:field>

                        <div class="flex">
                            <flux:spacer />

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
    @endvolt
</x-app-layout>