<?php

use App\Models\Gallery;
use App\Models\Photo;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Photo $photo;

    public Gallery $gallery;

    public ?string $htmlId = null;

    public $asFavorite = false;

    public $asCommented = false;

    public function mount()
    {
        $this->gallery = $this->photo->gallery;
    }

    #[On('gallery-sharing-changed')]
    #[On('gallery-cover-changed')]
    public function refreshGallery()
    {
        $this->gallery = $this->photo->gallery;
    }

    public function favorite()
    {
        $this->photo->toggleFavorite();

        $this->dispatch('photo-favorited');
    }

    public function setAsCover()
    {
        $this->authorize('updateCover', $this->gallery);

        abort_unless($this->photo->isImage(), 422);

        $this->gallery->setCoverPhoto($this->photo);

        $this->dispatch('gallery-cover-changed');
    }

    public function removeAsCover()
    {
        $this->authorize('updateCover', $this->gallery);

        if ($this->gallery->coverPhoto?->is($this->photo)) {
            $this->gallery->removeCoverPhoto();

            $this->dispatch('gallery-cover-changed');
        }
    }
}; ?>

<div
    class="group relative flex aspect-square overflow-hidden bg-zinc-100 dark:bg-white/10"
    x-data="{
        showActions: false,
        moreActionsOpen: false,
        downloadOptionsOpen: false,
    }"
    @mouseenter="showActions = true"
    @mouseleave="if (!moreActionsOpen && !downloadOptionsOpen) showActions = false"
    x-on:click.outside="moreActionsOpen = false; downloadOptionsOpen = false"
    data-photo-card
>
    <a
        id="{{ $htmlId }}"
        href="/galleries/{{ $gallery->id }}/photos/{{ $photo->id }}{{ $asCommented ? '?navigateCommented=true' : ($asFavorite ? '?navigateFavorites=true' : null) }}"
        wire:navigate
        class="mx-auto flex w-full"
    >
        @if ($photo->isImage())
            @if ($photo->small_thumbnail_url)
                <img
                    x-data="{ loaded: false, errored: false }"
                    x-init="if ($el.complete) loaded = true"
                    src="{{ $photo->small_thumbnail_url }}"
                    alt=""
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
            <div
                class="flex h-full w-full flex-col items-center justify-center gap-2 bg-zinc-100 px-2 dark:bg-white/10"
            >
                <x-icon name="document-text" class="size-10 text-zinc-400" />
                <x-ts-text class="w-full truncate text-center text-xs text-zinc-500 dark:text-white/70">
                    {{ $photo->name }}
                </x-ts-text>
            </div>
        @else
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
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
        <div class="absolute top-1.5 left-1.5 z-10 flex" :class="showActions ? 'flex' : 'hidden'">
            <x-ts-badge icon="chat-bubble-left" size="sm" color="zinc" variant="solid">
                {{ $photo->comments_count }}
            </x-ts-badge>
        </div>
    @endif

    @if (config('picstome.file_versions_enabled') && $photo->version_count > 1)
        <div class="absolute top-1.5 right-1.5 z-10 flex">
            <x-ts-badge icon="rectangle-stack" size="sm" color="zinc" variant="solid">
                {{ $photo->version_count }}
            </x-ts-badge>
        </div>
    @endif

    <div class="absolute right-1.5 bottom-1.5 flex flex-row-reverse gap-2" :class="showActions ? 'flex' : 'hidden'">
        <x-ts-button
            wire:click="favorite({{ $photo->id }})"
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
                @if ($gallery->coverPhoto?->is($this->photo))
                    <x-dropdown.items :text="__('Remove as Cover')" icon="x-mark" wire:click="removeAsCover" />
                @else
                    <x-dropdown.items :text="__('Set as Cover')" icon="star" wire:click="setAsCover" />
                @endif
            @endif

            <x-dropdown.items
                :text="__('Delete')"
                icon="trash"
                wire:click="$dispatch('request-delete-photo', { id: {{ $photo->id }} })"
                class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
            />
        </x-dropdown>
    </div>
</div>
