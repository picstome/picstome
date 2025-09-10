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

<div class="group relative aspect-square flex overflow-hidden bg-zinc-100 dark:bg-white/10" x-data="{ showActions: false, moreActionsOpen: false }" @mouseenter="showActions = true" @mouseleave="if (!moreActionsOpen) showActions = false">
    <a
        id="{{ $htmlId }}"
        href="/galleries/{{ $gallery->id }}/photos/{{ $photo->id }}{{ $asFavorite ? '?navigateFavorites=true' : null }}"
        wire:navigate
        class="mx-auto flex"
    >
        <img src="{{ $photo->thumbnail_url }}" alt="" class="object-cover" loading="lazy" />
    </a>
    <div class="absolute right-1.5 bottom-1.5 gap-2 flex flex-row-reverse" :class="showActions ? 'flex' : 'hidden'">
        <flux:button wire:click="favorite({{ $photo->id }})" square size="sm">
            @if ($photo->isFavorited())
                <flux:icon.heart class="size-5" variant="solid" />
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
                    <flux:menu.item
                        wire:click="removeAsCover"
                        icon="x-mark"
                    >
                        {{ __('Remove as Cover') }}
                    </flux:menu.item>
                @else
                    <flux:menu.item
                        wire:click="setAsCover"
                        icon="star"
                    >
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
