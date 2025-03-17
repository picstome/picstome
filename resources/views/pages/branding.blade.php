<?php

use App\Livewire\Forms\BrandingForm;
use App\Models\Team;
use Illuminate\View\View;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding');

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
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding')
        <div class="mx-auto max-w-xl">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Branding') }}</x-heading>
                    <x-subheading>{{ __('Customize your studio branding.') }}</x-subheading>
                </div>
            </div>

            <flux:separator class="my-10 mt-6" />

            <form wire:submit="save" class="space-y-6">
                @if ($team->brand_logo_url)
                    <img src="{{ $team->brand_logo_url }}" style="max-height: 35px" />
                @endif

                <flux:input wire:model="form.logo" :label="__('Logo')" type="file" accept="image/*" />

                @if ($team->brand_watermark_url)
                    <img src="{{ $team->brand_watermark_url }}" style="max-height: 35px" />
                @endif

                <flux:input wire:model="form.watermark" :label="__('Watermark')" type="file" accept="image/*" />

                <flux:radio.group wire:model="form.watermarkPosition" :label="__('Watermark position')">
                    <div class="flex gap-4 *:gap-x-2">
                        <flux:radio value="top" :label="__('Top')" />
                        <flux:radio value="bottom" :label="__('Bottom')" />
                        <flux:radio value="middle" :label="__('Middle')" />
                    </div>
                </flux:radio.group>

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

                <div class="flex">
                    <flux:spacer />

                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </div>
    @endvolt
</x-app-layout>
