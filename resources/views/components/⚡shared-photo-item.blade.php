<?php

use App\Models\Photo;
use Livewire\Component;

new class extends Component
{
    public Photo $photo;

    public ?string $htmlId = null;

    public $asFavorite = false;

    public $asCommented = false;

    public function favorite()
    {
        abort_unless($this->photo->gallery->is_share_selectable, 403);

        if ($this->photo->toggleFavoriteAndCheckSelectionLimit()) {
            $this->dispatch('selection-limit-reached');

            $this->photo->gallery->notifyOwnerWhenSelectionLimitReached();
        }

        $this->dispatch('photo-favorited');
    }
}; ?>

<div
    class="group relative flex aspect-square overflow-hidden bg-zinc-100 dark:bg-white/10"
    data-photo-card
>
    <a
        id="{{ $htmlId }}"
        wire:navigate
        href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $photo, 'navigateCommented' => $asCommented ? true : null, 'navigateFavorites' => $asFavorite ? true : null]) }}"
        class="mx-auto flex w-full"
    >
        @if ($photo->isImage())
            @if ($photo->small_thumbnail_url)
                <img
                    x-data="{ loaded: false, errored: false }"
                    x-init="if ($el.complete) loaded = true"
                    src="{{ $photo->small_thumbnail_url }}"
                    alt=""
                    @contextmenu.prevent
                    x-on:load="loaded = true"
                    x-on:error="errored = true"
                    class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10"
                    :class="loaded || errored ? '' : 'animate-pulse '"
                    loading="lazy"
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

        @if ($photo->gallery->is_share_watermarked && $photo->gallery->team->hasBrandWatermark())
            <x-watermark :team="$photo->gallery->team" />
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
        <div class="absolute top-1.5 left-1.5 z-10 flex">
            <x-ts-badge icon="chat-bubble-left" size="sm" color="zinc" variant="solid">
                {{ $photo->comments_count }}
            </x-ts-badge>
        </div>
    @endif

    {{-- Current file + retained versions, never the sequence counter. --}}
    @if (config('picstome.file_versions_enabled') && $photo->gallery->is_share_versions_visible && $photo->hasStoredVersions())
        <div class="absolute top-1.5 right-1.5 z-10 flex">
            <x-ts-badge icon="rectangle-stack" size="sm" color="zinc" variant="solid">
                {{ $photo->versionTotalCount() }}
            </x-ts-badge>
        </div>
    @endif

    <div class="absolute right-1.5 bottom-1.5 flex gap-2 transition-opacity md:opacity-0 md:group-hover:opacity-100">
        @if ($photo->gallery->is_share_selectable)
            <x-ts-button
                wire:click.preserve-scroll="favorite({{ $photo->id }})"
                variant="overlay"
                square
                size="sm"
                :aria-label="__('Favorite')"
            >
                @if ($photo->isFavorited())
                    <x-icon name="heart" solid class="size-5 text-red-500" />
                @else
                    <x-icon name="heart" class="size-5" />
                @endif
            </x-ts-button>
        @endif

        @if ($photo->gallery->is_share_downloadable)
            <x-ts-button
                :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                icon="cloud-arrow-down"
                variant="overlay"
                square
                size="sm"
                :aria-label="__('Download')"
            />
        @endif
    </div>
</div>
