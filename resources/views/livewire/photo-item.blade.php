<?php

use App\Models\Photo;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;

new class extends Component
{
    public Photo $photo;

    public ?string $htmlId = null;

    public $asFavorite = false;

    #[Computed]
    public function gallery()
    {
        return $this->photo->gallery;
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
    }

    public function removeAsCover()
    {
        $this->authorize('updateCover', $this->gallery);

        if ($this->gallery->coverPhoto?->is($this->photo)) {
            $this->gallery->removeCoverPhoto();
        }
    }
}; ?>

<div class="group relative aspect-square flex overflow-hidden bg-zinc-100 dark:bg-white/10" x-data="{ showActions: false, moreActionsOpen: false }" @mouseenter="showActions = true" @mouseleave="if (!moreActionsOpen) showActions = false">
    <a
        id="{{ $htmlId }}"
        href="/galleries/{{ $this->gallery->id }}/photos/{{ $photo->id }}{{ $asFavorite ? '?navigateFavorites=true' : null }}"
        wire:navigate.hover
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
            :href="route('galleries.photos.download', ['gallery' => $this->gallery, 'photo' => $photo])"
            icon="arrow-down-tray"
            square
            size="sm"
        />
        <flux:dropdown x-model="moreActionsOpen">
            <flux:button icon="ellipsis-vertical" square size="sm" />
            <flux:menu>
                <flux:menu.item
                    wire:click="$parent.deletePhoto({{ $photo->id }})"
                    wire:confirm="{{ __('Are you sure?') }}"
                    icon="trash"
                >
                    Delete
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
</div>
