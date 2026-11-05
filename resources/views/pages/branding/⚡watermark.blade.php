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

    public function save(): void
    {
        if (! $this->canSave()) {
            return;
        }

        $this->form->update();

        $this->form->watermark = null;

        $this->team = $this->team->fresh();

        $this->brandWatermarkPath = $this->team->brand_watermark_path;

        $this->form->setTeam($this->team);

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }

    public function canSave(): bool
    {
        if ($this->form->watermarkType === 'text') {
            return filled($this->form->watermarkText);
        }

        return $this->form->watermark !== null || filled($this->team->brand_watermark_path);
    }

    public function deleteWatermark(): void
    {
        $this->form->watermark = null;
        $this->form->watermarkText = null;

        $this->team->deleteBrandWatermark();

        $this->team = $this->team->fresh();

        $this->brandWatermarkPath = null;

        $this->form->setTeam($this->team);

        $this->toast()->success(__('The watermark has been deleted.'))->send();
    }

    public function mount(): void
    {
        $this->team = Auth::user()->currentTeam;

        $this->brandWatermarkPath = $this->team->brand_watermark_path;

        $this->form->setTeam($this->team);
    }
}; ?>

@php
    $previewOpacity = $form->watermarkTransparency === null || $form->watermarkTransparency === ''
        ? 1
        : max(0, min(1, (100 - (int) $form->watermarkTransparency) / 100));

    $previewImageUrl = $team->brand_watermark_image_url;

    if ($form->watermark) {
        try {
            $previewImageUrl = $form->watermark->temporaryUrl();
        } catch (\Throwable) {
            // Keep the saved image when a temporary preview URL is unavailable.
        }
    }

    $hasPreview = $form->watermarkType === 'text'
        ? filled($form->watermarkText)
        : filled($previewImageUrl);
    $watermarkFontQuery = collect(config('picstome.brand_fonts'))
        ->keys()
        ->map(fn (string $font): string => 'family='.str_replace(' ', '+', $font).':wght@400..900')
        ->implode('&');
@endphp

@push('head')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?{{ $watermarkFontQuery }}&display=swap" />
@endpush

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
                    @if ($hasPreview || $team->hasBrandWatermark())
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <x-ts-label>{{ __('Preview') }}</x-ts-label>
                                @if ($team->hasBrandWatermark())
                                    <x-ts-modal-trigger name="delete-watermark">
                                        <x-ts-button icon="trash" variant="subtle" size="sm" :tooltip="__('Delete')" />
                                    </x-ts-modal-trigger>
                                @endif
                            </div>
                            <div class="relative flex h-32 items-center justify-center overflow-hidden rounded-lg bg-zinc-800">
                                @if ($form->watermarkType === 'text')
                                    <x-watermark
                                        :team="$team"
                                        :position="$form->watermarkPosition"
                                        type="text"
                                        :text="$form->watermarkText"
                                        :font="$form->watermarkFont"
                                        :size="$form->watermarkSize"
                                        :opacity="$previewOpacity"
                                    />
                                @else
                                    <x-watermark
                                        :team="$team"
                                        :position="$form->watermarkPosition"
                                        type="image"
                                        :image-url="$previewImageUrl"
                                        :font="$form->watermarkFont"
                                        :size="$form->watermarkSize"
                                        :opacity="$previewOpacity"
                                    />
                                @endif
                            </div>
                        </div>
                    @endif

                    <x-ts-radio-group :label="__('Watermark type')">
                        <div class="flex gap-4 *:gap-x-2">
                            <x-ts-radio wire:model.live="form.watermarkType" name="form.watermarkType" value="image" :label="__('Image')" />
                            <x-ts-radio wire:model.live="form.watermarkType" name="form.watermarkType" value="text" :label="__('Text')" />
                        </div>
                    </x-ts-radio-group>

                    <div x-show="$wire.form.watermarkType === 'image'" x-cloak>
                        <x-branding-image-upload
                            property="form.watermark"
                            :label="__('Watermark image')"
                            :description="__('Applied to shared gallery photos when watermarking is enabled. Use a PNG or WebP with a transparent background; recommended height 32px.')"
                            :max-width="1200"
                            :max-height="32"
                        />
                    </div>

                    <div x-show="$wire.form.watermarkType === 'text'" x-cloak class="space-y-6">
                        <div class="space-y-1">
                            <x-ts-input
                                wire:model.live="form.watermarkText"
                                :label="__('Watermark text')"
                                :description="__('Displayed on shared gallery photos when watermarking is enabled. White text with a subtle shadow for contrast.')"
                                maxlength="100"
                            />
                            <x-ts-error :name="'form.watermarkText'" />
                        </div>

                        <x-ts-select wire:model.live="form.watermarkFont" :label="__('Watermark font')">
                            <option value="">{{ __('System sans-serif') }}</option>
                            @foreach (config('picstome.brand_fonts') as $value => $label)
                                <option value="{{ $value }}">{{ __($label) }}</option>
                            @endforeach
                        </x-ts-select>

                        <x-ts-radio-group :label="__('Watermark size')">
                            <div class="flex items-center gap-4 *:gap-x-2">
                                @foreach (config('picstome.watermark_sizes') as $value => $size)
                                    <x-ts-radio
                                        wire:model.live="form.watermarkSize"
                                        name="form.watermarkSize"
                                        :value="$value"
                                        :label="$size['short']"
                                        :title="__($size['label'])"
                                    />
                                @endforeach
                            </div>
                        </x-ts-radio-group>
                    </div>

                    <x-ts-radio-group :label="__('Watermark position')">
                        <div class="flex gap-4 *:gap-x-2">
                            <x-ts-radio wire:model.live="form.watermarkPosition" name="form.watermarkPosition" value="top" :label="__('Top')" />
                            <x-ts-radio wire:model.live="form.watermarkPosition" name="form.watermarkPosition" value="bottom" :label="__('Bottom')" />
                            <x-ts-radio wire:model.live="form.watermarkPosition" name="form.watermarkPosition" value="middle" :label="__('Middle')" />
                            <x-ts-radio wire:model.live="form.watermarkPosition" name="form.watermarkPosition" value="repeated" :label="__('Repeated')" />
                        </div>
                    </x-ts-radio-group>

                    <x-ts-field>
                        <x-ts-label :badge="__('Optional')">{{ __('Watermark transparency') }}</x-ts-label>
                        <x-ts-input-group>
                            <x-ts-input wire:model.live.debounce.500ms="form.watermarkTransparency"
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
                        x-bind:disabled="uploads > 0 || ($wire.form.watermarkType === 'text' ? ! ($wire.form.watermarkText || '').trim() : (! $wire.form.watermark && ! $wire.brandWatermarkPath))"
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
        :message="__('Deletes the watermark image or text. This action cannot be undone.')"
        :confirm-label="__('Delete')"
        method="deleteWatermark"
    />
</section>

@include('partials.branding-image-upload-script')
