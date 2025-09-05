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

name('branding.watermark');

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

        $this->redirectRoute('branding.watermark');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.watermark')
        <div>
            @include('partials.branding-header')

            <div class="flex">
                @include('partials.branding-nav')

            <!-- Main Content -->
            <div class="flex-1 p-6">
                <div class="mx-auto max-w-xl">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="max-sm:w-full sm:flex-1">
                            <x-heading level="1" size="xl">{{ __('Watermark') }}</x-heading>
                            <x-subheading>{{ __('Configure your watermark settings.') }}</x-subheading>
                        </div>
                    </div>

                    <flux:separator class="my-10 mt-6" />

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