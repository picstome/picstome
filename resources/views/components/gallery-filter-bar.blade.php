{{--
    Owner-only filter and sort controls for the gallery wall.

    Deliberately not a pill: the tabs and these controls share one sticky row,
    so the bar is a compact group of icon buttons on the right. Every button has
    a tooltip because most of them are icon-only. The star filter opens a
    popover with the five stars and filters by the exact star count.

    Favourites are not a filter here: the Favorites tab applies the favourites
    filter on the All photos grid (see showFavorites()).
--}}

@props([
    'rating' => null,
    'sortField' => 'name',
    'sortDirection' => 'asc',
    'allCount' => 0,
    'allTotal' => 0,
])

@php
    $sortLabels = [
        'name' => __('Name'),
        'date' => __('Date added'),
        'rating' => __('Rating'),
        'favorites' => __('Favorites'),
        'size' => __('File size'),
        'versions' => __('Versions'),
    ];

    $sortIcons = [
        'name' => 'language',
        'date' => 'calendar-days',
        'rating' => 'star',
        'favorites' => 'heart',
        'size' => 'circle-stack',
        'versions' => 'rectangle-stack',
    ];

    $hasFilters = $rating !== null;
    $directionLabel = $sortDirection === 'asc' ? __('Sort ascending') : __('Sort descending');
    $ratingLabel = $rating === null ? __('Any rating') : trans_choice(':count star|:count stars', $rating);
@endphp

<div {{ $attributes->class(['flex items-center gap-0.5']) }} data-gallery-filter-bar>
    {{-- Rating filter popover --}}
    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        x-on:keydown.escape.window="open = false"
        class="relative"
    >
        <button
            type="button"
            x-on:click="open = ! open"
            x-tooltip="{{ $rating === null ? __('Filter by rating') : __('Rating filter: :value', ['value' => $ratingLabel]) }}"
            aria-label="{{ __('Filter by rating') }}"
            @class([
                'inline-flex h-8 items-center gap-1 rounded-full px-2 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-star/60',
                'bg-brand-star/10 text-brand-star' => $rating !== null,
                'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white' => $rating === null,
            ])
            data-rating-filter-trigger
        >
            <x-icon name="star" solid class="size-4 {{ $rating !== null ? 'text-brand-star' : 'text-zinc-400 dark:text-zinc-500' }}" />
            @if ($rating !== null)
                <span class="tabular-nums">{{ $rating }}</span>
            @endif
            <x-icon name="chevron-down" class="size-3 opacity-60" />
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.origin.top.right
            class="absolute top-full right-0 z-30 mt-2 w-64 rounded-2xl border border-zinc-200/80 bg-white/95 p-3 shadow-2xl backdrop-blur-md dark:border-white/10 dark:bg-zinc-900/95"
            data-rating-filter-popover
        >
            <div class="flex items-center justify-between gap-2">
                <x-rating-stars :rating="$rating" interactive method="filterByRating" :show-clear="false" />
            </div>

            <div class="mt-2 flex items-center justify-between border-t border-zinc-200/80 px-1 pt-2 dark:border-white/10">
                <button
                    type="button"
                    wire:click="clearRatingFilter"
                    x-tooltip="{{ __('Show photos with any rating') }}"
                    class="rounded-full px-2 py-0.5 text-[0.7rem] font-semibold text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white"
                    data-rating-filter-clear
                >
                    {{ __('Any rating') }}
                </button>

                <span class="text-[0.65rem] text-zinc-400 dark:text-zinc-500">{{ __('1–5 stars') }}</span>
            </div>
        </div>
    </div>

    <span aria-hidden="true" class="mx-0.5 h-5 w-px shrink-0 bg-zinc-200 dark:bg-white/15"></span>

    {{-- Sort field --}}
    <x-dropdown position="bottom-end">
        <x-slot:action>
            <button
                type="button"
                x-on:click="show = ! show"
                x-tooltip="{{ __('Sort by :field', ['field' => $sortLabels[$sortField] ?? __('Name')]) }}"
                aria-label="{{ __('Sort photos') }}"
                class="inline-flex h-8 items-center gap-1.5 rounded-full px-2 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white"
                data-sort-trigger
            >
                <x-icon name="{{ $sortIcons[$sortField] ?? 'bars-arrow-up' }}" class="size-4" />
                <span class="hidden sm:inline">{{ $sortLabels[$sortField] ?? __('Name') }}</span>
                <x-icon name="chevron-down" class="size-3 opacity-60" />
            </button>
        </x-slot:action>

        @foreach ($sortLabels as $field => $label)
            <x-dropdown.items
                :text="$label"
                :icon="$sortField === $field ? 'check' : ($sortIcons[$field] ?? 'bars-arrow-up')"
                wire:click="sortBy('{{ $field }}')"
                data-sort-field="{{ $field }}"
            />
        @endforeach
    </x-dropdown>

    {{-- Direction toggle --}}
    <button
        type="button"
        wire:click="toggleSortDirection"
        x-tooltip="{{ $directionLabel }}"
        aria-label="{{ $directionLabel }}"
        class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/60 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white"
        data-sort-direction
    >
        <x-icon name="{{ $sortDirection === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down' }}" class="size-4" />
    </button>

    @if ($hasFilters)
        <span
            class="hidden text-xs text-zinc-500 sm:inline dark:text-zinc-400"
            x-tooltip="{{ __('Showing :filtered of :total photos', ['filtered' => $allCount, 'total' => $allTotal]) }}"
            data-filter-count
        >
            {{ $allCount }}/{{ $allTotal }}
        </span>

        <button
            type="button"
            wire:click="clearFilters"
            x-tooltip="{{ __('Clear all filters') }}"
            aria-label="{{ __('Clear all filters') }}"
            class="inline-flex size-8 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/60 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white"
            data-clear-filters
        >
            <x-icon name="x-mark" class="size-4" />
        </button>
    @endif
</div>
