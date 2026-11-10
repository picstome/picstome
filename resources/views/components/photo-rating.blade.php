{{--
    Rating entry point for the single-photo viewer and read-only displays.

    `variant="dock"` renders one compact dock button (star + current rating)
    that opens the five-star picker upwards. The inline strip made the dock too
    long on mobile; most photographers rate with the 1-5 keys anyway, and the
    popover is there for the mouse.

    `variant="display"` is the static row used by the photo info panel.
--}}

@props([
    'rating' => null,
    'variant' => 'display',
    'compact' => false,
])

@php
    $rating = $rating === null ? null : max(0, min(5, (int) $rating));
@endphp

@if ($variant === 'dock')
    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.window="open = false"
        class="relative"
        data-photo-rating
    >
        <x-dock-button
            :label="__('Rating')"
            hint="1–5"
            x-on:click="open = ! open"
            data-dock-rating
        >
            <span class="relative inline-flex items-center gap-0.5">
                <x-icon name="star" solid class="size-4 {{ $rating ? 'text-brand-star' : 'text-zinc-400 dark:text-zinc-500' }}" />
                @if ($rating)
                    <span class="text-[0.65rem] font-bold tabular-nums">{{ $rating }}</span>
                @endif
            </span>
        </x-dock-button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.bottom
            x-on:click="open = false"
            class="absolute bottom-full left-1/2 z-40 mb-2 w-max max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-2xl border border-zinc-200/80 bg-white/95 p-2.5 shadow-2xl backdrop-blur-md dark:border-white/10 dark:bg-zinc-900/95"
            data-rating-popover
        >
            <x-rating-stars :rating="$rating" interactive :show-clear="false" />

            <div class="mt-1 flex items-center justify-between gap-4 border-t border-zinc-200/80 px-1 pt-2 dark:border-white/10">
                <span class="text-[0.65rem] text-zinc-500 dark:text-zinc-400">{{ __('1–5 · 0 clears') }}</span>

                @if ($rating)
                    <button
                        type="button"
                        wire:click="setRating(0)"
                        class="rounded-full px-2 py-0.5 text-[0.7rem] font-semibold text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white"
                        data-rating-popover-clear
                    >
                        {{ __('Clear rating') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@else
    <x-rating-stars :rating="$rating" :compact="$compact" />
@endif
