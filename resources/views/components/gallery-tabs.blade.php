{{--
    Gallery media tabs shared by the owner view and the public shared gallery.

    On mobile the labels are visually hidden so the tab bar only shows icons and
    fits without horizontal scrolling. The full label stays available to screen
    readers through aria-label.
--}}

@props([
    'favoritesCount' => 0,
    'selectionLimit' => null,
    'commentsCount' => 0,
    'showFavorites' => true,
    'showComments' => true,
    'downloadUrl' => null,
    'showDownload' => false,
])

@php
    $favoritesLabel = $selectionLimit
        ? __('Favorites (:selected/:limit)', [
            'selected' => $favoritesCount,
            'limit' => $selectionLimit,
        ])
        : __('Favorites (:selected)', ['selected' => $favoritesCount]);

    $commentsLabel = $commentsCount > 0
        ? __('Comments (:count)', ['count' => $commentsCount])
        : __('Comments');

    $tabClass = 'inline-flex items-center gap-2 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-zinc-500 transition hover:text-zinc-800 data-[current=true]:border-brand-green data-[current=true]:text-brand-green dark:text-zinc-400 dark:hover:text-white dark:data-[current=true]:border-brand-sage dark:data-[current=true]:text-brand-sage';
@endphp

<nav
    class="flex items-center gap-1 overflow-x-auto border-b border-zinc-800/10 dark:border-white/20"
    role="tablist"
>
    <button
        type="button"
        @click="$wire.activeTab = 'all'"
        x-bind:data-current="$wire.activeTab === 'all'"
        class="{{ $tabClass }}"
        aria-label="{{ __('All photos') }}"
        title="{{ __('All photos') }}"
    >
        <x-icon name="camera" class="size-5" />
        <span class="max-sm:sr-only">{{ __('All photos') }}</span>
    </button>

    @if ($showFavorites)
        <button
            type="button"
            @click="$wire.activeTab = 'favorited'"
            x-bind:data-current="$wire.activeTab === 'favorited'"
            class="{{ $tabClass }}"
            aria-label="{{ $favoritesLabel }}"
            title="{{ $favoritesLabel }}"
        >
            <x-icon name="heart" class="size-5" />
            <span class="max-sm:sr-only">{{ $favoritesLabel }}</span>
        </button>

        {{ $afterFavorites ?? '' }}
    @endif

    @if ($showComments)
        <button
            type="button"
            @click="$wire.activeTab = 'commented'"
            x-bind:data-current="$wire.activeTab === 'commented'"
            class="{{ $tabClass }}"
            aria-label="{{ $commentsLabel }}"
            title="{{ $commentsLabel }}"
        >
            <x-icon name="envelope" class="size-5" />
            <span class="max-sm:sr-only">{{ $commentsLabel }}</span>
        </button>
    @endif

    @if ($showDownload || $downloadUrl)
        <button
            type="button"
            @click="$wire.activeTab = 'download'"
            x-bind:data-current="$wire.activeTab === 'download'"
            class="{{ $tabClass }}"
            style="background-color: var(--color-primary-500); color: white;"
            aria-label="{{ __('Download') }}"
            title="{{ __('Download') }}"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-5"
                aria-hidden="true"
            >
                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" />
                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7" />
                <path d="M7 3v4a1 1 0 0 0 1 1h7" />
            </svg>
            <span class="max-sm:sr-only">{{ __('Download') }}</span>
        </button>
    @endif
</nav>
