{{--
    Shared modal used by the gallery grid and the owner photo viewer to pick
    the focal point of the gallery cover. The parent component owns the
    `adjustingCover` flag and the `setCoverFocalPoint` action.
--}}
@props([
    'gallery',
    'photo' => null,
    'adjusting' => false,
])

@if ($gallery->coverPhoto && $adjusting)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4" wire:key="cover-focus-overlay">
        <div class="flex min-h-full items-center justify-center">
            <div
                class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-4 shadow-xl dark:bg-zinc-900"
                x-data="{ x: {{ $gallery->cover_focal_x ?? 0.5 }}, y: {{ $gallery->cover_focal_y ?? 0.5 }} }"
            >
            <div class="mb-3 flex items-center justify-between">
                <x-ts-heading size="md" level="2">{{ __('Adjust cover focus') }}</x-ts-heading>
                <x-ts-button variant="subtle" size="sm" icon="x-mark" wire:click="$set('adjustingCover', false)" />
            </div>
            <x-ts-text class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Click on the image to choose the area that should stay visible.') }}
            </x-ts-text>
            <div class="relative overflow-hidden rounded-lg">
                <img
                    src="{{ $photo && $photo->gallery?->coverPhoto?->is($photo) ? $photo->large_thumbnail_url : $gallery->coverPhoto?->cover_image_url }}"
                    alt=""
                    class="mx-auto max-h-[55vh] w-auto max-w-full cursor-crosshair object-contain"
                    @click="
                        const rect = $el.getBoundingClientRect();
                        x = (event.clientX - rect.left) / rect.width;
                        y = (event.clientY - rect.top) / rect.height;
                    "
                />
                <span
                    class="pointer-events-none absolute size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-brand-green shadow"
                    :style="`left: ${x * 100}%; top: ${y * 100}%;`"
                ></span>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <x-ts-button variant="subtle" wire:click="$set('adjustingCover', false)">
                    {{ __('Cancel') }}
                </x-ts-button>
                <x-ts-button variant="primary" class="btn-ink" @click="$wire.setCoverFocalPoint(x, y).then(() => $wire.$set('adjustingCover', false))">
                    {{ __('Confirm') }}
                </x-ts-button>
            </div>
            </div>
        </div>
    </div>
@endif