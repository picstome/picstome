{{--
    Gallery media tabs shared by the owner view and the public shared gallery.

    On mobile the labels are visually hidden so the tab bar only shows icons and
    fits without horizontal scrolling. The full label stays available to screen
    readers through aria-label.

    `favoritesAsFilter` makes the Favorites tab a shortcut for the favourites
    filter on All photos instead of a separate panel (owner gallery). The shared
    gallery keeps the dedicated Favorites tab by leaving it false.

    `bordered` is disabled when the parent already draws the sticky row border.
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
    'favoritesAsFilter' => false,
    'favoritesFilterActive' => false,
    'bordered' => true,
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

    $tabClassFor = fn (string $tab, ?bool $active = null): string => $tabClass.(($active ?? ($activeTab === $tab)) ? $activeClass : '');

    // When Favorites is a filter instead of a panel, its button toggles the
    // filter on the All photos grid and the two buttons share the active state.
    $allClick = $favoritesAsFilter ? '$wire.showAllPhotos()' : '$wire.activeTab = \'all\'';
    $allCurrent = $favoritesAsFilter
        ? '$wire.activeTab === \'all\' && ! $wire.favoritesOnly'
        : '$wire.activeTab === \'all\'';
    $favoritesClick = $favoritesAsFilter ? '$wire.showFavorites()' : '$wire.activeTab = \'favorited\'';
    $favoritesCurrent = $favoritesAsFilter
        ? '$wire.activeTab === \'all\' && $wire.favoritesOnly'
        : '$wire.activeTab === \'favorited\'';
    $tabClick = fn (string $tab): string => $favoritesAsFilter
        ? '$wire.showTab(\''.$tab.'\')'
        : '$wire.activeTab = \''.$tab.'\'';
@endphp

<nav
    class="{{ $variant === 'pills' ? 'flex flex-wrap items-center justify-center gap-2' : 'flex items-center gap-1 overflow-x-auto '.($bordered ? 'border-b border-zinc-800/10 dark:border-white/20' : '') }} {{ $centered ? 'justify-center' : '' }}"
    role="tablist"
>
    <button
        type="button"
        @click="{{ $allClick }}; $dispatch('gallery-tab-changed')"
        x-bind:data-current="{{ $allCurrent }}"
        class="{{ $tabClassFor('all', $favoritesAsFilter ? ! $favoritesFilterActive : null) }}"
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
            @click="{{ $tabClick('archived') }}; $dispatch('gallery-tab-changed')"
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
            @click="{{ $favoritesClick }}; $dispatch('gallery-tab-changed')"
            x-bind:data-current="{{ $favoritesCurrent }}"
            class="{{ $tabClassFor('favorited', $favoritesAsFilter ? $favoritesFilterActive : null) }}"
            aria-label="{{ $favoritesLabel }}"
            title="{{ $favoritesLabel }}"
            data-favorites-tab
        >
            <x-icon name="heart" class="size-5" />
            <span class="max-sm:sr-only">{{ $favoritesLabel }}</span>
        </button>

        {{ $afterFavorites ?? '' }}
    @endif

    @if ($showComments)
        <button
            type="button"
            @click="{{ $tabClick('commented') }}; $dispatch('gallery-tab-changed')"
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
            @click="{{ $tabClick('sync') }}; $dispatch('gallery-tab-changed')"
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
            @click="{{ $tabClick('download') }}; $dispatch('gallery-tab-changed')"
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
