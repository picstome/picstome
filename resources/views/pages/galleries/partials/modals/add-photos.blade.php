    <x-ts-modal name="add-photos" class="w-full sm:max-w-lg">
        <div class="space-y-6" id="add-photos-uploader" data-gallery-id="{{ $gallery->id }}" data-gallery-name="{{ $gallery->name }}">
            <div>
                <x-ts-heading size="lg">{{ __('Add media') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Upload images or videos to your gallery.') }}
                </x-ts-subheading>
            </div>

            @if (auth()->user()?->currentTeam?->storageUsedPercentage() >= config('picstome.storage_warning_percent', 95))
                <x-ts-callout icon="bolt" variant="secondary">
                    <x-ts-callout-heading>{{ __('Low storage space') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('You have less than 5% storage remaining. ') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Upgrade') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            @if ($gallery->keep_original_size)
                <x-ts-callout icon="information-circle" variant="secondary" class="mb-4">
                    <x-ts-callout-heading>{{ __('Original size enabled') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('There is a maximum photo pixel limit of :max pixels because original size is enabled for this gallery.', ['max' => number_format(config('picstome.max_photo_pixels') / 1000000, 0).'M']) }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            {{-- The queue lives in the persistent upload dock (layout), so this
                 modal is only the file chooser. It closes as soon as the files
                 are handed over to the uploader. --}}
            <x-ts-input
                x-on:change="
                    const files = $event.target.files;
                    if (files && files.length > 0) {
                        window.PicstomeUploads.enqueue(files, {
                            galleryId: $el.closest('[data-gallery-id]').dataset.galleryId,
                            galleryName: $el.closest('[data-gallery-name]').dataset.galleryName,
                        });
                        $tsui.close.modal('add-photos');
                    }
                    $event.target.value = '';
                "
                type="file"
                data-test="upload-files"
                accept="{{ '.'.implode(',.', config('picstome.upload_extensions')) }}"
                multiple
            />
            <x-ts-description class="mt-2 max-sm:hidden">
                {{ __('Drag and drop files anywhere on the screen, or click on choose files. Supported formats: JPG, JPEG, PNG, TIFF, MP4, WEBM, OGG, PDF, and RAW files (CR2, CR3, NEF, ARW, DNG, ORF, RW2, PEF, SRW, MOS, MRW, 3FR).') }}
            </x-ts-description>
        </div>
    </x-ts-modal>
