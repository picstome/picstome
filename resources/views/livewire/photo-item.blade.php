<?php

use App\Models\Photo;
use Livewire\Volt\Component;

new class extends Component
{
    public Photo $photo;

    public $asFavorite = false;

    public function favorite()
    {
        $this->photo->toggleFavorite();

        $this->dispatch('photo-favorited');
    }
}; ?>

<div class="group relative flex overflow-hidden rounded-lg bg-zinc-100 dark:bg-white/10">
    <a
        href="/galleries/{{ $photo->gallery->id }}/photos/{{ $photo->id }}{{ $asFavorite ? '?navigateFavorites=true' : null }}"
        wire:navigate.hover
        class="mx-auto flex"
    >
        <img src="{{ $photo->thumbnail_url }}" alt="" class="object-contain" />
    </a>
    <div class="absolute right-1.5 bottom-1.5 hidden gap-2 group-hover:flex">
        <flux:button wire:click="favorite({{ $photo->id }})" square size="sm">
            @if ($photo->isFavorited())
                <flux:icon.heart class="size-5" variant="solid" />
            @else
                <flux:icon.heart class="size-5" />
            @endif
        </flux:button>
        <flux:button
            :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
            icon="arrow-down-tray"
            square
            size="sm"
        />
        <flux:button
            wire:click="$parent.deletePhoto({{ $photo->id }})"
            wire:confirm="{{ __('Are you sure?') }}"
            icon="trash"
            square
            size="sm"
        />
    </div>
</div>
