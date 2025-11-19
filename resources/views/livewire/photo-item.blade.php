<?php

use App\Models\Gallery;
use App\Models\Photo;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

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
    x-data="{ showActions: false, moreActionsOpen: false }"
    @mouseenter="showActions = true"
    @mouseleave="if (!moreActionsOpen) showActions = false"
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
            <video
                class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10"
                controls
            >
                <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                Your browser does not support the video tag.
            </video>
        @else
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
        @endif
    </a>
    @if (($photo->comments_count ?? 0) > 0)
        <div class="absolute top-1.5 left-1.5 z-10 flex" :class="showActions ? 'flex' : 'hidden'">
            <flux:badge icon="chat-bubble-left" size="sm" color="zinc" variant="solid">
                {{ $photo->comments_count }}
            </flux:badge>
        </div>
    @endif

    <div class="absolute right-1.5 bottom-1.5 flex flex-row-reverse gap-2" :class="showActions ? 'flex' : 'hidden'">
        <flux:button wire:click="favorite({{ $photo->id }})" square size="sm">
            @if ($photo->isFavorited())
                <flux:icon.heart class="size-5 text-red-500" variant="solid" />
            @else
                <flux:icon.heart class="size-5" />
            @endif
        </flux:button>
        <flux:button
            :href="route('galleries.photos.download', ['gallery' => $gallery, 'photo' => $photo])"
            icon="arrow-down-tray"
            square
            size="sm"
        />
        <flux:dropdown x-model="moreActionsOpen">
            <flux:button icon="ellipsis-vertical" square size="sm" />
            <flux:menu>
                @if ($gallery->coverPhoto?->is($this->photo))
                    <flux:menu.item wire:click="removeAsCover" icon="x-mark">
                        {{ __('Remove as Cover') }}
                    </flux:menu.item>
                @else
                    <flux:menu.item wire:click="setAsCover" icon="star">
                        {{ __('Set as Cover') }}
                    </flux:menu.item>
                @endif

                <flux:menu.item
                    wire:click="$parent.deletePhoto({{ $photo->id }})"
                    wire:confirm="{{ __('Are you sure?') }}"
                    icon="trash"
                    variant="danger"
                >
                    {{ __('Delete') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
</div>
