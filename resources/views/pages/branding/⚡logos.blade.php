<?php

use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\LogosForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use Interactions;
    use WithFileUploads;

    public Team $team;

    public LogosForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->toast()->success(__('Your changes have been saved.'))->send();
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
            <x-ts-heading>{{ __('Logos') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Upload and manage your studio logos.') }}</x-ts-subheading>

            <div class="mt-5 w-full max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    @if ($team->brand_logo_icon_url)
                        <div class="space-y-2">
                            <x-ts-label>{{ __('Current Logo Icon') }}</x-ts-label>
                            <img src="{{ $team->brand_logo_icon_url }}" class="size-[80px]" />
                        </div>
                    @endif

                    <div>
                        <x-ts-input
                            wire:model="form.logoIcon"
                            :label="__('Logo Icon')"
                            :description="__('Your logo at 1:1 aspect ratio.')"
                            type="file"
                            accept="image/*"
                        />
                        <x-ts-text wire:loading wire:target="form.logoIcon" class="mt-2">
                            {{ __('Uploading...') }}
                        </x-ts-text>
                    </div>

                    @if ($team->brand_logo_url)
                        <div class="space-y-2">
                            <x-ts-label>{{ __('Current Logo') }}</x-ts-label>
                            <img src="{{ $team->brand_logo_url }}" class="max-h-[80px]" />
                        </div>
                    @endif

                    <div>
                        <x-ts-input
                            wire:model="form.logo"
                            :label="__('Logo')"
                            :description="__('Full version of your logo.')"
                            type="file"
                            accept="image/*"
                        />
                        <x-ts-text wire:loading wire:target="form.logo" class="mt-2">
                            {{ __('Uploading...') }}
                        </x-ts-text>
                    </div>

                    <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                </form>
            </div>
        </div>
    </div>
</section>
