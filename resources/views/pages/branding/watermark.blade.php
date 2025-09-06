<?php

use App\Livewire\Forms\WatermarkForm;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding.watermark');

middleware('auth');

new class extends Component
{
    use WithFileUploads;

    public Team $team;

    public WatermarkForm $form;

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
    @volt('pages.branding.watermark')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('Watermark') }}</flux:heading>
                    <flux:subheading>{{ __('Configure your watermark settings.') }}</flux:subheading>

                    <div class="mt-5 w-full max-w-lg">
                        <form wire:submit="save" class="space-y-6">
                            @if ($team->brand_watermark_url)
                                <div class="space-y-2">
                                    <flux:label>{{ __('Current Watermark') }}</flux:label>
                                    <img src="{{ $team->brand_watermark_url }}" style="max-height: 35px" />
                                </div>
                            @endif

                            <flux:input wire:model="form.watermark" :label="__('Watermark')" type="file" accept="image/*" />

                            <flux:radio.group wire:model="form.watermarkPosition" :label="__('Watermark position')">
                                <div class="flex gap-4 *:gap-x-2">
                                    <flux:radio value="top" :label="__('Top')" />
                                    <flux:radio value="bottom" :label="__('Bottom')" />
                                    <flux:radio value="middle" :label="__('Middle')" />
                                </div>
                            </flux:radio.group>

                            <flux:field>
                                <flux:label :badge="__('Optional')">{{ __('Watermark transparency') }}</flux:label>
                                <flux:input.group>
                                    <flux:input wire:model="form.watermarkTransparency"
                                                type="number" min="0" max="100" class="max-w-20" />
                                    <flux:input.group.suffix>%</flux:input.group.suffix>
                                </flux:input.group>
                            </flux:field>

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>