{{--
    Five-star row shared by the rating popovers, the photo info panel and the
    read-only summaries.

    Interactive mode expects to live inside a Livewire component: every star
    calls `method(n)` on the parent, so the dock can rate-and-advance and the
    filter bar can filter. `filledOnly` renders just the earned stars, which is
    what the gallery thumbnails use to stay unobtrusive.

    Stars are warm gold (`--color-brand-star`); empty stars stay neutral so an
    unrated photo never looks half-selected.
--}}

@props([
    'rating' => null,
    'interactive' => false,
    'compact' => false,
    'filledOnly' => false,
    'method' => 'setRating',
    'showClear' => true,
    'clearMethod' => 'setRating',
    'clearArgument' => '0',
])

@php
    $starSize = $compact ? 'size-3.5' : 'size-5';
    $rating = $rating === null ? null : max(0, min(5, (int) $rating));
    $stars = $filledOnly ? ($rating ? range(1, $rating) : []) : range(1, 5);
@endphp

<div
    {{ $attributes->class(['flex items-center gap-0.5']) }}
    @if ($interactive) x-data="{ hover: 0 }" @endif
    data-rating-stars
>
    @foreach ($stars as $star)
        @php $filled = $rating !== null && $star <= $rating; @endphp

        @if ($interactive)
            <button
                type="button"
                wire:click="{{ $method }}({{ $star }})"
                x-on:mouseenter="hover = {{ $star }}"
                x-on:mouseleave="hover = 0"
                aria-pressed="{{ $filled ? 'true' : 'false' }}"
                aria-label="{{ trans_choice(':count star|:count stars', $star) }}"
                x-tooltip="{{ trans_choice(':count star|:count stars', $star) }}"
                class="grid size-8 place-items-center rounded-full transition hover:bg-brand-star/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-star/60"
                data-rating-star="{{ $star }}"
            >
                <x-icon
                    name="star"
                    solid
                    class="{{ $starSize }} transition-transform duration-150 {{ $filled ? 'text-brand-star drop-shadow-[0_1px_2px_rgba(232,180,74,0.35)]' : 'text-zinc-300 dark:text-white/30' }}"
                    x-bind:class="hover >= {{ $star }} ? 'text-brand-star! scale-110' : ''"
                />
            </button>
        @else
            @if ($filled)
                <x-icon name="star" solid class="{{ $starSize }} text-brand-star" />
            @else
                <x-icon name="star" class="{{ $starSize }} text-zinc-300 dark:text-white/25" />
            @endif
        @endif
    @endforeach

    @if ($interactive && $showClear && $rating !== null)
        <button
            type="button"
            wire:click="{{ $clearMethod }}({{ $clearArgument }})"
            class="ms-0.5 grid size-6 place-items-center rounded-full text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/60 dark:hover:bg-white/10 dark:hover:text-white"
            aria-label="{{ __('Clear rating') }}"
            title="{{ __('Clear rating (0)') }}"
            data-rating-clear
        >
            <x-icon name="x-mark" class="size-3.5" />
        </button>
    @endif
</div>
