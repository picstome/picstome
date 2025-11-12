<?php

use App\Livewire\Forms\StylingForm;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding.styling');

middleware('auth');

new class extends Component
{
    use WithFileUploads;

    public Team $team;

    public StylingForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        Flux::toast(__('Your changes have been saved.'), variant: 'success');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.styling')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('Styling') }}</flux:heading>
                    <flux:subheading>{{ __('Customize the visual style of your studio.') }}</flux:subheading>

                    <div class="mt-5 w-full max-w-lg">
                        <form wire:submit="save" class="space-y-6">
                            <flux:select wire:model="form.color" :label="__('Accent color')" class="flex-wrap">
                                <option value="red">{{ __('Red') }}</option>
                                <option value="orange">{{ __('Orange') }}</option>
                                <option value="amber">{{ __('Amber') }}</option>
                                <option value="yellow">{{ __('Yellow') }}</option>
                                <option value="lime">{{ __('Lime') }}</option>
                                <option value="green">{{ __('Green') }}</option>
                                <option value="emerald">{{ __('Emerald') }}</option>
                                <option value="teal">{{ __('Teal') }}</option>
                                <option value="cyan">{{ __('Cyan') }}</option>
                                <option value="sky">{{ __('Sky') }}</option>
                                <option value="blue">{{ __('Blue') }}</option>
                                <option value="indigo">{{ __('Indigo') }}</option>
                                <option value="violet">{{ __('Violet') }}</option>
                                <option value="purple">{{ __('Purple') }}</option>
                                <option value="fuchsia">{{ __('Fuchsia') }}</option>
                                <option value="pink">{{ __('Pink') }}</option>
                                <option value="rose">{{ __('Rose') }}</option>
                                <option value="">{{ __('Zinc') }}</option>
                            </flux:select>

                            <flux:select wire:model="form.font" :label="__('Font')">
                                <option value="">System sans-serif</option>
                                <option value="Roboto Flex">Roboto</option>
                                <option value="Raleway">Raleway</option>
                                <option value="Montserrat">Montserrat</option>
                                <option value="Work Sans">Work Sans</option>
                                <option value="Source Sans 3">Source Sans</option>
                                <option value="Nunito Sans">Nunito Sans</option>
                                <option value="Source Serif 4">Source Serif</option>
                                <option value="Roboto Serif">Roboto Serif</option>
                                <option value="Playfair Display">Playfair Display</option>
                            </flux:select>

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>