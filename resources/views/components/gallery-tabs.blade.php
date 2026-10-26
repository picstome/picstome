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
@endphp

<flux:navbar
    scrollable
    class="border-b border-zinc-800/10 dark:border-white/20 max-sm:[&_[data-content]]:sr-only"
>
    <flux:navbar.item
        @click="$wire.activeTab = 'all'"
        x-bind:data-current="$wire.activeTab === 'all'"
        icon="camera"
        aria-label="{{ __('All photos') }}"
        title="{{ __('All photos') }}"
    >
        {{ __('All photos') }}
    </flux:navbar.item>

    @if ($showFavorites)
        <flux:navbar.item
            @click="$wire.activeTab = 'favorited'"
            x-bind:data-current="$wire.activeTab === 'favorited'"
            icon="heart"
            aria-label="{{ $favoritesLabel }}"
            title="{{ $favoritesLabel }}"
        >
            {{ $favoritesLabel }}
        </flux:navbar.item>

        {{ $afterFavorites ?? '' }}
    @endif

    @if ($showComments)
        <flux:navbar.item
            @click="$wire.activeTab = 'commented'"
            x-bind:data-current="$wire.activeTab === 'commented'"
            icon="envelope"
            aria-label="{{ $commentsLabel }}"
            title="{{ $commentsLabel }}"
        >
            {{ $commentsLabel }}
        </flux:navbar.item>
    @endif

    @if ($downloadUrl)
        <flux:navbar.item
            @click="$wire.activeTab = 'download'"
            x-bind:data-current="$wire.activeTab === 'download'"
            style="background-color: var(--color-accent); color: var(--color-accent-foreground, white);"
            aria-label="{{ __('Download') }}"
            title="{{ __('Download') }}"
        >
            <x-slot name="icon">
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
            </x-slot>
            {{ __('Download') }}
        </flux:navbar.item>
    @endif
</flux:navbar>
