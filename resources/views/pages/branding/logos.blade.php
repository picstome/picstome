<?php

use App\Livewire\Forms\LogosForm;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding.logos');

middleware('auth');

new class extends Component
{
    use WithFileUploads;

    public Team $team;

    public LogosForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        Flux::toast('Your changes have been saved.', variant: 'success');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.logos')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('Logos') }}</flux:heading>
                    <flux:subheading>{{ __('Upload and manage your studio logos.') }}</flux:subheading>

                    <div class="mt-5 w-full max-w-lg">
                        <form wire:submit="save" class="space-y-6">
                            @if ($team->brand_logo_icon_url)
                                <div class="space-y-2">
                                    <flux:label>{{ __('Current Logo Icon') }}</flux:label>
                                    <img src="{{ $team->brand_logo_icon_url }}" class="size-[80px]" />
                                </div>
                            @endif

                            <flux:input wire:model="form.logoIcon" :label="__('Logo Icon')" :description="__('Your logo at 1:1 aspect ratio.')" type="file" accept="image/*" />

                            @if ($team->brand_logo_url)
                                <div class="space-y-2">
                                    <flux:label>{{ __('Current Logo') }}</flux:label>
                                    <img src="{{ $team->brand_logo_url }}" class="max-h-[80px]" />
                                </div>
                            @endif

                            <flux:input wire:model="form.logo" :label="__('Logo')" :description="__('Full version of your logo.')" type="file" accept="image/*" />

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>
