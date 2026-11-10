{{--
    Floating file info panel for the single-photo viewer.

    Photographer only: the shared client viewer does not include it, because
    it exposes file facts the client has no reason to see.

    Collapsed by default and built on native `<details>`: no script, nothing to
    initialise, and no reactive class fights inside Livewire re-renders. The
    open/closed state is remembered in localStorage, because a photographer
    checking the same data across a whole shoot should not reopen it every
    photo.

    `wire:ignore.self` is required: rating or favoriting a photo re-renders the
    panel with a new row, and morphing the `<details>` element itself drops its
    native `open` attribute, collapsing a panel the photographer opened. The
    directive keeps the element and its state while still updating the rows
    inside it.

    Rows whose value is null are skipped, so a photo without stored dimensions
    simply does not show that line instead of showing "unknown".
--}}
@props([
    'photo',
    'facts',
    'commentsCount' => 0,
])

<div
    class="pointer-events-none absolute top-0 right-0 z-30 hidden max-w-xs p-3 lg:block"
    data-photo-info
>
    <details
        class="group pointer-events-auto w-64 overflow-hidden rounded-xl border border-zinc-200/80 bg-white/90 shadow-xl backdrop-blur-md dark:border-white/10 dark:bg-zinc-900/90"
        x-data
        x-init="$el.open = window.localStorage.getItem('picstome-photo-info-open') === '1'"
        x-on:toggle="window.localStorage.setItem('picstome-photo-info-open', $el.open ? '1' : '0')"
        wire:ignore.self
        data-photo-info-details
    >
        <summary
            class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 transition hover:bg-zinc-100/70 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden"
            data-photo-info-toggle
        >
            <x-icon name="document-text" class="size-4 shrink-0 text-zinc-400" />
            <span class="min-w-0 flex-1 truncate text-xs font-medium text-zinc-800 dark:text-white">
                {{ $facts['name'] }}
            </span>
            <x-icon
                name="chevron-down"
                class="size-3.5 shrink-0 text-zinc-400 transition group-open:rotate-180"
            />
        </summary>

        <div
            class="border-t border-zinc-200/80 px-3 py-2 dark:border-white/10"
            data-photo-info-body
        >
            <dl class="space-y-1.5 text-[0.7rem]">
                @if ($facts['resolution'])
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Resolution') }}</dt>
                        <dd class="font-medium text-zinc-800 tabular-nums dark:text-white">{{ $facts['resolution'] }}</dd>
                    </div>
                @endif

                @if ($facts['size'])
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('File size') }}</dt>
                        <dd class="font-medium text-zinc-800 tabular-nums dark:text-white">{{ $facts['size'] }}</dd>
                    </div>
                @endif

                @if ($photo->isRated())
                    <div class="flex items-center justify-between gap-3" data-photo-info-rating>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Rating') }}</dt>
                        <dd class="flex items-center gap-1.5">
                            <x-photo-rating :rating="$photo->rating" compact />
                            <span class="text-[0.65rem] font-semibold text-zinc-500 tabular-nums dark:text-zinc-400">
                                {{ $photo->rating }}/5
                            </span>
                        </dd>
                    </div>
                @endif

                @if ($commentsCount > 0)
                    <div class="flex justify-between gap-3" data-photo-info-comments>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Comments') }}</dt>
                        <dd class="font-medium text-zinc-800 tabular-nums dark:text-white">{{ $commentsCount }}</dd>
                    </div>
                @endif

                @if ($photo->favorited_at)
                    <div class="flex justify-between gap-3" data-photo-info-favorite>
                        <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">{{ __('Favorite since') }}</dt>
                        <dd class="text-right font-medium text-zinc-800 dark:text-white">{{ $photo->favorited_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif

                @if ($photo->gallery->coverPhoto?->is($photo))
                    <div class="flex items-center justify-between gap-3" data-photo-info-cover>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Cover') }}</dt>
                        <dd class="flex items-center gap-1 font-medium text-zinc-800 dark:text-white">
                            <x-icon name="photo" class="size-3.5 text-brand-green dark:text-brand-sage" />
                            {{ __('Yes') }}
                        </dd>
                    </div>
                @endif

                @if ($photo->archived_at)
                    <div class="flex justify-between gap-3" data-photo-info-archived>
                        <dt class="shrink-0 text-zinc-500 dark:text-zinc-400">{{ __('Archived since') }}</dt>
                        <dd class="text-right font-medium text-zinc-800 dark:text-white">{{ $photo->archived_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif

                <div class="flex justify-between gap-3">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</dt>
                    <dd class="font-medium text-zinc-800 dark:text-white">{{ $facts['type'] }}</dd>
                </div>

                @if ($facts['uploaded'])
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Uploaded') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">{{ $facts['uploaded'] }}</dd>
                    </div>
                @endif

                @if ($facts['ratio'])
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Aspect ratio') }}</dt>
                        <dd class="font-medium text-zinc-800 tabular-nums dark:text-white">{{ $facts['ratio'] }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </details>
</div>
