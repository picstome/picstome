@props([
    'photo',
    'gallery',
    'context' => 'owner',
    'htmlId' => null,
    'asFavorite' => false,
    'asCommented' => false,
    'index' => 0,
])

@php
    $isOwner = $context === 'owner';
    $isShared = $context === 'shared';
    $isPortfolio = $context === 'portfolio';
    $isSelectable = $isShared && $gallery->is_share_selectable;
    $isDownloadable = $isShared && $gallery->is_share_downloadable;
    $isReady = $photo->isReady();
    $hasWatermark = $isShared
        && $gallery->is_share_watermarked
        && $gallery->team?->brand_watermark_url;
    $imageSizes = $isPortfolio
        ? '(max-width: 768px) 33vw, (max-width: 1024px) 25vw, 16vw'
        : '(max-width: 640px) 33vw, (max-width: 1024px) 25vw, 16vw';
    $placeholder = asset('app-logo-icon.png');
    $statusLabel = match ($photo->status) {
        'pending' => __('Queued'),
        'processing' => __('Processing...'),
        'warming' => __('Warming CDN...'),
        'failed' => __('Processing failed'),
        default => __('Uploading...'),
    };

    $link = match (true) {
        $isOwner => route('galleries.photos.show', [
            'gallery' => $gallery,
            'photo' => $photo,
            'navigateFavorites' => $asFavorite ? true : null,
            'navigateCommented' => $asCommented ? true : null,
        ]),
        $isPortfolio => route('portfolio.photos.show', [
            'handle' => $gallery->team->handle,
            'gallery' => $gallery,
            'photo' => $photo,
        ]),
        default => route('shares.photos.show', [
            'gallery' => $gallery,
            'photo' => $photo,
            'navigateCommented' => $asCommented ? true : null,
            'navigateFavorites' => $asFavorite ? true : null,
        ]),
    };
@endphp

<div
    class="group relative flex aspect-square overflow-hidden bg-zinc-100 dark:bg-white/10"
    x-data="{
        showActions: false,
        moreActionsOpen: false,
        downloadOptionsOpen: false,
    }"
    @if ($isOwner)
        @mouseenter="showActions = true"
        @mouseleave="if (!moreActionsOpen && !downloadOptionsOpen) showActions = false"
        x-on:click.outside="moreActionsOpen = false; downloadOptionsOpen = false"
    @endif
    data-photo-card
    wire:key="{{ $htmlId ?? 'photo-'.$photo->id }}"
>
    @if (! $isReady)
        <div class="relative h-full w-full bg-zinc-200 dark:bg-white/10">
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>

            @if ($isOwner)
                <div class="absolute inset-0 flex items-center justify-center bg-black/40 px-2 text-center text-xs font-medium text-white">
                    {{ $statusLabel }}
                </div>
            @endif
        </div>
    @else
        <a
            id="{{ $htmlId }}"
            href="{{ $link }}"
            wire:navigate
            class="mx-auto flex w-full"
        >
            @if ($photo->isImage())
                @if ($photo->small_thumbnail_url)
                    <img
                        x-data="{ loaded: false, errored: false, fallback: '{{ $placeholder }}' }"
                        x-init="if ($el.complete) loaded = true"
                        src="{{ $photo->small_thumbnail_url }}"
                        srcset="{{ $photo->small_thumbnail_url }} 500w, {{ $photo->thumbnail_url }} 1000w"
                        sizes="{{ $imageSizes }}"
                        alt=""
                        @if ($isShared) @contextmenu.prevent @endif
                        x-on:load="loaded = true"
                        x-on:error="errored = true; if ($el.src !== fallback) $el.src = fallback"
                        class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10"
                        :class="loaded || errored ? '' : 'animate-pulse'"
                        loading="{{ $index < 8 ? 'eager' : 'lazy' }}"
                        @if ($index === 0) fetchpriority="high" @endif
                        decoding="async"
                    />
                @else
                    <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
                @endif
            @elseif ($photo->isVideo())
                <video class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10" muted playsinline preload="metadata">
                    <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                    Your browser does not support the video tag.
                </video>
            @elseif ($photo->isPdf())
                <div class="flex h-full w-full flex-col items-center justify-center gap-2 bg-zinc-100 px-2 dark:bg-white/10">
                    <x-icon name="document-text" class="size-10 text-zinc-400" />
                    <x-ts-text class="w-full truncate text-center text-xs text-zinc-500 dark:text-white/70">
                        {{ $photo->name }}
                    </x-ts-text>
                </div>
            @else
                <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
            @endif

            @if ($hasWatermark)
                @if ($gallery->team->brand_watermark_position === 'repeated')
                    <div
                        class="pointer-events-none absolute inset-0"
                        style="
                            background-image: url('{{ $gallery->team->brand_watermark_url }}');
                            background-repeat: repeat;
                            background-size: 75%;
                            opacity: {{ $gallery->team->brand_watermark_transparency ? (100 - $gallery->team->brand_watermark_transparency) / 100 : 1 }};
                        "
                    ></div>
                @else
                    <div
                        @class([
                            'absolute flex justify-center',
                            'inset-x-0 bottom-0' => $gallery->team->brand_watermark_position === 'bottom',
                            'inset-x-0 top-0' => $gallery->team->brand_watermark_position === 'top',
                            'inset-0 flex items-center' => $gallery->team->brand_watermark_position === 'middle',
                        ])
                    >
                        <img
                            class="max-w-full"
                            src="{{ $gallery->team->brand_watermark_url }}"
                            alt=""
                            style="
                                opacity: {{ $gallery->team->brand_watermark_transparency ? (100 - $gallery->team->brand_watermark_transparency) / 100 : 1 }};
                            "
                            loading="lazy"
                        />
                    </div>
                @endif
            @endif
        </a>

        @if ($photo->isVideo())
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-black/55 text-white shadow-lg backdrop-blur-sm">
                    <x-icon name="play" variant="solid" class="size-5 translate-x-px" />
                </span>
            </div>
        @endif

        @if (($photo->comments_count ?? 0) > 0)
            <div class="absolute top-1.5 left-1.5 z-10 flex" @if ($isOwner) :class="showActions ? 'flex' : 'hidden'" @endif>
                <x-ts-badge icon="chat-bubble-left" size="sm" color="zinc" variant="solid">
                    {{ $photo->comments_count }}
                </x-ts-badge>
            </div>
        @endif

        @if (config('picstome.file_versions_enabled') && $photo->version_count > 1 && ($isOwner || $gallery->is_share_versions_visible))
            <div class="absolute top-1.5 right-1.5 z-10 flex">
                <x-ts-badge icon="rectangle-stack" size="sm" color="zinc" variant="solid">
                    {{ $photo->version_count }}
                </x-ts-badge>
            </div>
        @endif

        @if ($isOwner)
            <div class="absolute right-1.5 bottom-1.5 flex flex-row-reverse gap-2" :class="showActions ? 'flex' : 'hidden'">
                <x-ts-button
                    wire:click="toggleFavorite({{ $photo->id }})"
                    variant="overlay"
                    square
                    size="sm"
                    :aria-label="__('Favorite')"
                >
                    @if ($photo->isFavorited())
                        <x-icon name="heart" class="size-5 text-red-500" variant="solid" />
                    @else
                        <x-icon name="heart" class="size-5" />
                    @endif
                </x-ts-button>

                @if ($photo->path && $photo->raw_path)
                    <x-dropdown
                        position="bottom-end"
                        x-on:open="downloadOptionsOpen = $event.detail.status"
                        x-on:select="downloadOptionsOpen = false"
                    >
                        <x-slot:action>
                            <x-ts-button
                                icon="arrow-down-tray"
                                variant="overlay"
                                square
                                size="sm"
                                :aria-label="__('Download')"
                                x-on:click="show = ! show; $refs.dropdown.dispatchEvent(new CustomEvent('open', { detail: { status: show } }))"
                            />
                        </x-slot:action>

                        <x-dropdown.items
                            :text="__('Download JPG')"
                            icon="arrow-down-tray"
                            :href="route('galleries.photos.download', ['gallery' => $gallery, 'photo' => $photo, 'type' => 'jpg'])"
                        />
                        <x-dropdown.items
                            :text="__('Download Raw')"
                            icon="arrow-down-tray"
                            :href="route('galleries.photos.download', ['gallery' => $gallery, 'photo' => $photo, 'type' => 'raw'])"
                        />
                    </x-dropdown>
                @else
                    <x-ts-button
                        :href="route('galleries.photos.download', ['gallery' => $gallery, 'photo' => $photo])"
                        icon="arrow-down-tray"
                        variant="overlay"
                        square
                        size="sm"
                        :aria-label="__('Download')"
                    />
                @endif

                <x-dropdown
                    position="bottom-end"
                    x-on:open="moreActionsOpen = $event.detail.status"
                    x-on:select="moreActionsOpen = false"
                >
                    <x-slot:action>
                        <x-ts-button
                            icon="ellipsis-vertical"
                            variant="overlay"
                            square
                            size="sm"
                            :aria-label="__('More actions')"
                            data-photo-actions
                            x-on:click="show = ! show; $refs.dropdown.dispatchEvent(new CustomEvent('open', { detail: { status: show } }))"
                        />
                    </x-slot:action>

                    @if ($photo->isImage())
                        @if ($gallery->coverPhoto?->is($photo))
                            <x-dropdown.items :text="__('Remove as Cover')" icon="x-mark" wire:click="removeAsCover({{ $photo->id }})" />
                        @else
                            <x-dropdown.items :text="__('Set as Cover')" icon="star" wire:click="setAsCover({{ $photo->id }})" />
                        @endif
                    @endif

                    <x-dropdown.items
                        :text="__('Delete')"
                        icon="trash"
                        wire:click="requestDeletePhoto({{ $photo->id }})"
                        class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                    />
                </x-dropdown>
            </div>
        @elseif ($isSelectable || $isDownloadable)
            <div class="absolute right-1.5 bottom-1.5 hidden gap-2 group-hover:flex">
                @if ($isSelectable)
                    <x-ts-button
                        wire:click="toggleFavorite({{ $photo->id }})"
                        variant="overlay"
                        square
                        size="sm"
                        :aria-label="__('Favorite')"
                    >
                        @if ($photo->isFavorited())
                            <x-icon name="heart" class="size-5 text-red-500" variant="solid" />
                        @else
                            <x-icon name="heart" class="size-5" />
                        @endif
                    </x-ts-button>
                @endif

                @if ($isDownloadable)
                    <x-ts-button
                        :href="route('shares.photos.download', ['gallery' => $gallery, 'photo' => $photo])"
                        icon="arrow-down-tray"
                        variant="overlay"
                        square
                        size="sm"
                        :aria-label="__('Download')"
                    />
                @endif
            </div>
        @endif
    @endif
</div>
