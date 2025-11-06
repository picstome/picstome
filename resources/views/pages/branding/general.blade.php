<?php

use App\Livewire\Forms\GeneralForm;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('branding.general');

middleware('auth');

new class extends Component
{
    use WithFileUploads;

    public Team $team;

    public GeneralForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        Flux::toast(__('Your changes have been saved.'), variant: 'success');

        $this->redirectRoute('branding.general', navigate: true);
    }

    public function resetDismissedSetupSteps()
    {
        $this->team->dismissed_setup_steps = [];
        $this->team->save();
        Flux::toast(__('Setup steps have been reset.'), variant: 'success');
        $this->team = $this->team->fresh();
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

                             <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                         </form>

                        <flux:separator variant="subtle" class="my-8" />

                         <flux:button wire:click="resetDismissedSetupSteps" variant="subtle" inset="left">
                             {{ __('Reset setup steps') }}
                         </flux:button>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>
