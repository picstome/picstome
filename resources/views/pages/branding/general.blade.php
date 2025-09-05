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
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('General Settings') }}</flux:heading>
                    <flux:subheading>{{ __('Basic branding information for your studio.') }}</flux:subheading>

                    <div class="mt-5 w-full max-w-lg">
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

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>