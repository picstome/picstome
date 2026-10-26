{{--
    Notice shown when a visitor reaches the photo selection limit of a shared
    gallery. Replaces the native browser alert with a branded dialog.
--}}

@props(['limit' => null])

<flux:modal name="selection-limit" class="w-full sm:max-w-md">
    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <span
                class="bg-brand-coral/10 text-brand-coral dark:bg-brand-coral/15 dark:text-brand-coral flex size-9 shrink-0 items-center justify-center rounded-full"
            >
                <flux:icon.exclamation-triangle class="size-5" />
            </span>

            <div>
                <flux:heading size="lg">{{ __('Selection limit reached') }}</flux:heading>

                <flux:subheading class="mt-1">
                    {{ $limit
                        ? __('You have reached the limit of :limit photos.', ['limit' => $limit])
                        : __('You have reached the limit for photo selection.') }}
                </flux:subheading>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="primary" class="btn-ink">{{ __('Accept') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
