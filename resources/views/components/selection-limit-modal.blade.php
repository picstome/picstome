{{--
    Notice shown when a visitor reaches the photo selection limit of a shared
    gallery. Replaces the native browser alert with a branded dialog.
--}}

@props(['limit' => null])

<x-ts-modal name="selection-limit" class="w-full sm:max-w-md">
    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <span
                class="bg-brand-coral/10 text-brand-coral dark:bg-brand-coral/15 dark:text-brand-coral flex size-9 shrink-0 items-center justify-center rounded-full"
            >
                <x-icon name="exclamation-triangle" class="size-5" />
            </span>

            <div>
                <x-ts-heading size="lg">{{ __('Selection limit reached') }}</x-ts-heading>

                <x-ts-subheading class="mt-1">
                    {{ $limit
                        ? __('You have reached the limit of :limit photos.', ['limit' => $limit])
                        : __('You have reached the limit for photo selection.') }}
                </x-ts-subheading>
            </div>
        </div>

        <div class="flex gap-2">
            <x-ts-spacer />

            <x-ts-modal-close>
                <x-ts-button variant="primary" class="btn-ink">{{ __('Accept') }}</x-ts-button>
            </x-ts-modal-close>
        </div>
    </div>
</x-ts-modal>
