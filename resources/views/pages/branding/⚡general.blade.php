<?php

use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\GeneralForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use Interactions;
    use WithFileUploads;

    public Team $team;

    public GeneralForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->toast()->success(__('Your changes have been saved.'))->send();

        $this->redirectRoute('branding.general', navigate: true);
    }

    public function resetDismissedSetupSteps()
    {
        $this->team->dismissed_setup_steps = [];
        $this->team->save();
        $this->toast()->success(__('Setup steps have been reset.'))->send();
        $this->team = $this->team->fresh();
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<section class="mx-auto max-w-6xl">
    @include('partials.branding-header')

    <div class="flex items-start max-md:flex-col">
        <div class="mr-10 w-full pb-4 md:w-[220px]">
            @include('partials.branding-nav')
        </div>

        <x-ts-separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <x-ts-heading>{{ __('General Settings') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Basic branding information for your studio.') }}</x-ts-subheading>

            <div class="mt-5 w-full max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    <x-ts-input wire:model="form.name" :label="__('Studio name')" />

                    <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                </form>

                <x-ts-separator variant="subtle" class="my-8" />

                <x-ts-button wire:click="resetDismissedSetupSteps" variant="subtle" inset="left">
                    {{ __('Reset setup steps') }}
                </x-ts-button>
            </div>
        </div>
    </div>
</section>
