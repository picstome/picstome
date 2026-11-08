@props([
    'photo',
    'gallery',
    'context' => 'owner',
    'htmlId' => null,
    'asFavorite' => false,
    'asCommented' => false,
    'asArchived' => false,
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
        && $gallery->team?->hasBrandWatermark();
    $imageSizes = $isOwner
        ? '(max-width: 640px) 50vw, (max-width: 1280px) 33vw, 25vw'
        : '(max-width: 640px) 100vw, (max-width: 768px) 50vw, (max-width: 1024px) 33vw, (max-width: 1536px) 25vw, 20vw';
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
            'navigateArchived' => $asArchived ? true : null,
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
    class="group relative mb-1 break-inside-avoid overflow-hidden bg-zinc-100 dark:bg-white/10"
    x-data="{
        showActions: ! window.matchMedia('(hover: hover)').matches,
    }"
    @if ($isOwner)
        @mouseenter="showActions = true"
        @mouseleave="showActions = false"
    @endif
    data-photo-card
    data-photo-id="{{ $photo->id }}"
    wire:key="{{ $htmlId ?? 'photo-'.$photo->id }}"
>
    @if (! $isReady)
        <div class="relative aspect-[3/4] w-full bg-zinc-200 dark:bg-white/10">
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
            class="block w-full"
        >
            @if ($photo->isImage())
                @if ($photo->small_thumbnail_url)
                    {{-- Reserve a portrait placeholder until the image reports its
                         natural ratio; keep it when the thumbnail cannot load. --}}
                    <img
                        x-data="{ loaded: false, errored: false, fallback: '{{ $placeholder }}' }"
                        x-init="if ($el.complete) { if ($el.naturalWidth > 0) { loaded = true } else { errored = true; $el.src = fallback } }"
                        src="{{ $photo->small_thumbnail_url }}"
                        srcset="{{ $photo->small_thumbnail_url }} 500w, {{ $photo->thumbnail_url }} 1000w"
                        sizes="{{ $imageSizes }}"
                        alt=""
                        @if ($isShared) @contextmenu.prevent @endif
                        x-on:load="loaded = true"
                        x-on:error="errored = true; if ($el.src !== fallback) $el.src = fallback"
                        class="block h-auto w-full bg-zinc-300 object-contain dark:bg-white/10"
                        :class="{
                            'animate-pulse': ! loaded && ! errored,
                            'aspect-[3/4]': ! loaded || errored,
                        }"
                        loading="{{ $index < 8 ? 'eager' : 'lazy' }}"
                        @if ($index === 0) fetchpriority="high" @endif
                        decoding="async"
                    />
                @else
                    <div class="aspect-[3/4] w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
                @endif
            @elseif ($photo->isVideo())
                <video class="block h-auto w-full bg-zinc-300 dark:bg-white/10" muted playsinline preload="metadata">
                    <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                    Your browser does not support the video tag.
                </video>
            @elseif ($photo->isPdf())
                <div class="flex aspect-[3/4] w-full flex-col items-center justify-center gap-2 bg-zinc-100 px-2 dark:bg-white/10">
                    <x-icon name="document-text" class="size-10 text-zinc-400" />
                    <x-ts-text class="w-full truncate text-center text-xs text-zinc-500 dark:text-white/70">
                        {{ $photo->name }}
                    </x-ts-text>
                </div>
            @else
                <div class="aspect-[3/4] w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
            @endif

            @if ($hasWatermark && $gallery->team)
                <x-watermark :team="$gallery->team" />
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
            <x-photo-mini-dock x-bind:class="showActions ? 'flex' : 'hidden'">
                <x-dock-button
                    wire:click="toggleFavorite({{ $photo->id }})"
                    icon="heart"
                    :label="__('Favorite')"
                    :tone="$photo->isFavorited() ? 'favorite' : 'default'"
                    compact
                />

                <x-dock-button
                    wire:click="toggleArchive({{ $photo->id }})"
                    icon="archive-box"
                    :label="$photo->isArchived() ? __('Unarchive') : __('Archive')"
                    :tone="$photo->isArchived() ? 'active' : 'default'"
                    compact
                    data-photo-archive
                />

                <x-dock-button
                    wire:click="requestDeletePhoto({{ $photo->id }})"
                    icon="trash"
                    :label="__('Delete')"
                    tone="danger"
                    compact
                    data-photo-delete
                />

                @if ($photo->isImage())
                    @if ($gallery->coverPhoto?->is($photo))
                        <x-dock-button
                            icon="star"
                            :label="__('Adjust cover focus')"
                            tone="active"
                            compact
                            wire:click="$set('adjustingCover', true)"
                            data-photo-cover
                        />
                    @else
                        <x-dock-button
                            icon="star"
                            :label="__('Set as cover')"
                            compact
                            wire:click="setAsCover({{ $photo->id }})"
                            data-photo-cover
                        />
                    @endif
                @endif
            </x-photo-mini-dock>
        @elseif ($isSelectable || $isDownloadable)
            <x-photo-mini-dock class="transition-opacity md:opacity-0 md:group-hover:opacity-100">
                @if ($isSelectable)
                    <x-dock-button
                        wire:click="toggleFavorite({{ $photo->id }})"
                        icon="heart"
                        :label="__('Favorite')"
                        :tone="$photo->isFavorited() ? 'favorite' : 'default'"
                        compact
                    />
                @endif

                @if ($isDownloadable)
                    <x-dock-button
                        :href="route('shares.photos.download', ['gallery' => $gallery, 'photo' => $photo])"
                        icon="cloud-arrow-down"
                        :label="__('Download')"
                        tone="accent"
                        compact
                    />
                @endif
            </x-photo-mini-dock>
        @endif
    @endif
</div>
