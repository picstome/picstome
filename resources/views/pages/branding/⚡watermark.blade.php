<?php

use App\Livewire\Forms\WatermarkForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use WithFileUploads;

    public Team $team;

    public WatermarkForm $form;

    public ?string $brandWatermarkPath = null;

    public function save()
    {
        if (! $this->canSave()) {
            return;
        }

        $this->form->update();

        $this->team = $this->team->fresh();

        $this->brandWatermarkPath = $this->team->brand_watermark_path;

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }

    public function canSave(): bool
    {
        return $this->form->watermark !== null || $this->team->brand_watermark_url !== null;
    }

    public function deleteWatermark(): void
    {
        $this->form->watermark = null;

        $this->team->deleteBrandWatermark();

        $this->team = $this->team->fresh();

        $this->brandWatermarkPath = null;

        $this->toast()->success(__('The image has been deleted.'))->send();
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->brandWatermarkPath = $this->team->brand_watermark_path;

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
                            <div class="flex items-start justify-between gap-3">
                                <x-ts-label>{{ __('Current Watermark') }}</x-ts-label>
                                <x-ts-modal-trigger name="delete-watermark">
                                    <x-ts-button icon="trash" variant="subtle" size="sm" :tooltip="__('Delete')" />
                                </x-ts-modal-trigger>
                            </div>
                            <div class="overflow-hidden overflow-x-scroll">
                                <img src="{{ $team->brand_watermark_url }}" class="max-w-none" />
                            </div>
                        </div>
                    @endif

                    <x-branding-image-upload
                        property="form.watermark"
                        :label="__('Watermark')"
                        :description="__('Applied to shared gallery photos when watermarking is enabled. Use a PNG or WebP with a transparent background; recommended height 32px.')"
                        :max-width="1200"
                        :max-height="32"
                    />

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

                    <x-ts-button
                        type="submit"
                        variant="primary"
                        :disabled="! $this->canSave()"
                        x-data="{ uploads: 0 }"
                        x-on:livewire-upload-start.window="uploads++"
                        x-on:livewire-upload-finish.window="uploads = Math.max(0, uploads - 1)"
                        x-on:livewire-upload-error.window="uploads = Math.max(0, uploads - 1)"
                        x-bind:disabled="uploads > 0 || (! $wire.form.watermark && ! $wire.brandWatermarkPath)"
                        x-bind:class="uploads > 0 ? 'bg-amber-500! hover:bg-amber-600!' : ''"
                    >
                        <span x-show="uploads === 0">{{ __('Save') }}</span>
                        <span x-show="uploads > 0" x-cloak>{{ __('Uploading...') }}</span>
                    </x-ts-button>
                </form>
            </div>
        </div>
    </div>

    <x-confirm-modal
        name="delete-watermark"
        :title="__('Delete watermark?')"
        :message="__('This action cannot be undone.')"
        :confirm-label="__('Delete')"
        method="deleteWatermark"
    />
</section>

@include('partials.branding-image-upload-script')
