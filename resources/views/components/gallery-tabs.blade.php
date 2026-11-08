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
    'archivedCount' => 0,
    'showFavorites' => true,
    'showComments' => true,
    'showSync' => false,
    'downloadUrl' => null,
    'showDownload' => false,
    'variant' => 'underline',
    'centered' => false,
    'activeTab' => 'all',
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

    $archivedLabel = __('Archived (:count)', ['count' => $archivedCount]);

    if ($variant === 'pills') {
        $tabClass = 'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-zinc-700 bg-white shadow-sm ring-1 ring-zinc-200 cursor-pointer transition hover:bg-zinc-50 data-[current=true]:bg-primary-500 data-[current=true]:text-white data-[current=true]:ring-primary-500 data-[current=true]:shadow-md dark:text-zinc-200 dark:bg-white/10 dark:ring-white/10 dark:hover:bg-white/20';
        $activeClass = ' bg-primary-500 text-white ring-primary-500 shadow-md';
    } else {
        $tabClass = 'inline-flex items-center gap-2 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-zinc-500 cursor-pointer transition hover:text-zinc-800 data-[current=true]:border-brand-green data-[current=true]:text-brand-green dark:text-zinc-400 dark:hover:text-white dark:data-[current=true]:border-brand-sage dark:data-[current=true]:text-brand-sage';
        $activeClass = ' border-brand-green text-brand-green dark:border-brand-sage dark:text-brand-sage';
    }

    $tabClassFor = fn (string $tab): string => $tabClass.($activeTab === $tab ? $activeClass : '');
@endphp

<nav
    class="{{ $variant === 'pills' ? 'flex flex-wrap items-center justify-center gap-2' : 'flex items-center gap-1 overflow-x-auto border-b border-zinc-800/10 dark:border-white/20' }} {{ $centered ? 'justify-center' : '' }}"
    role="tablist"
>
    <button
        type="button"
        @click="$wire.activeTab = 'all'; $dispatch('gallery-tab-changed')"
        x-bind:data-current="$wire.activeTab === 'all'"
        class="{{ $tabClassFor('all') }}"
        aria-label="{{ __('All photos') }}"
        title="{{ __('All photos') }}"
    >
        <x-icon name="camera" class="size-5" />
        <span class="max-sm:sr-only">{{ __('All photos') }}</span>
    </button>

    {{-- Owner only: the archived tab only exists when something is archived. --}}
    @if ($archivedCount > 0)
        <button
            type="button"
            @click="$wire.activeTab = 'archived'; $dispatch('gallery-tab-changed')"
            x-bind:data-current="$wire.activeTab === 'archived'"
            class="{{ $tabClassFor('archived') }}"
            aria-label="{{ $archivedLabel }}"
            title="{{ $archivedLabel }}"
            data-archived-tab
        >
            <x-icon name="archive-box" class="size-5" />
            <span class="max-sm:sr-only">{{ $archivedLabel }}</span>
        </button>
    @endif

    @if ($showFavorites)
        <button
            type="button"
            @click="$wire.activeTab = 'favorited'; $dispatch('gallery-tab-changed')"
            x-bind:data-current="$wire.activeTab === 'favorited'"
            class="{{ $tabClassFor('favorited') }}"
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
            @click="$wire.activeTab = 'commented'; $dispatch('gallery-tab-changed')"
            x-bind:data-current="$wire.activeTab === 'commented'"
            class="{{ $tabClassFor('commented') }}"
            aria-label="{{ $commentsLabel }}"
            title="{{ $commentsLabel }}"
        >
            <x-icon name="envelope" class="size-5" />
            <span class="max-sm:sr-only">{{ $commentsLabel }}</span>
        </button>
    @endif

    @if ($showSync)
        <button
            type="button"
            @click="$wire.activeTab = 'sync'; $dispatch('gallery-tab-changed')"
            x-bind:data-current="$wire.activeTab === 'sync'"
            class="{{ $tabClassFor('sync') }}"
            aria-label="{{ __('Sync') }}"
            title="{{ __('Sync') }}"
        >
            <x-icon name="arrow-path" class="size-5" />
            <span class="max-sm:sr-only">{{ __('Sync') }}</span>
        </button>
    @endif

    @if ($showDownload || $downloadUrl)
        <button
            type="button"
            @click="$wire.activeTab = 'download'; $dispatch('gallery-tab-changed')"
            x-bind:data-current="$wire.activeTab === 'download'"
            class="{{ $tabClassFor('download') }}"
            style="background-color: var(--color-primary-500); color: white;"
            aria-label="{{ __('Download') }}"
            title="{{ __('Download') }}"
        >
            <x-icon name="cloud-arrow-down" class="size-5" />
            <span class="max-sm:sr-only">{{ __('Download') }}</span>
        </button>
    @endif
</nav>
