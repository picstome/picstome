<?php

use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\WatermarkForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use Interactions;
    use WithFileUploads;

    public Team $team;

    public WatermarkForm $form;

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
            <x-ts-heading>{{ __('Watermark') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Configure your watermark settings.') }}</x-ts-subheading>

            <div class="mt-5 w-full max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    @if ($team->brand_watermark_url)
                        <div class="space-y-2">
                            <x-ts-label>{{ __('Current Watermark') }}</x-ts-label>
                            <div class="overflow-hidden overflow-x-scroll">
                                <img src="{{ $team->brand_watermark_url }}" class="max-w-none" />
                            </div>
                        </div>
                    @endif

                    <div>
                        <x-ts-input wire:model="form.watermark" :label="__('Watermark')" type="file" accept="image/*" :description:trailing="__('The recommended watermark height is 32px.')" />

                        <x-ts-text wire:loading wire:target="form.watermark" class="mt-2">{{ __('Uploading...') }}</x-ts-text>
                    </div>

                    <x-ts-radio-group :label="__('Watermark position')">
                        <div class="flex gap-4 *:gap-x-2">
                            <x-ts-radio wire:model="form.watermarkPosition" name="form.watermarkPosition" value="top" :label="__('Top')" />
                            <x-ts-radio wire:model="form.watermarkPosition" name="form.watermarkPosition" value="bottom" :label="__('Bottom')" />
                            <x-ts-radio wire:model="form.watermarkPosition" name="form.watermarkPosition" value="middle" :label="__('Middle')" />
                            <x-ts-radio wire:model="form.watermarkPosition" name="form.watermarkPosition" value="repeated" :label="__('Repeated')" />
                        </div>
                    </x-ts-radio-group>

                    <x-ts-field>
                        <x-ts-label :badge="__('Optional')">{{ __('Watermark transparency') }}</x-ts-label>
                        <x-ts-input-group>
                            <x-ts-input wire:model="form.watermarkTransparency"
                                        type="number" min="0" max="100" class="max-w-20" />
                            <x-ts-input-group-suffix>%</x-ts-input-group-suffix>
                        </x-ts-input-group>
                    </x-ts-field>

                    <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                </form>
            </div>
        </div>
    </div>
</section>
