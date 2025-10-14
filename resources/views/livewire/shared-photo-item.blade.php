<?php

use App\Models\Photo;
use Livewire\Volt\Component;

new class extends Component
{
    public Photo $photo;

    public ?string $htmlId = null;

    public $asFavorite = false;

    public function favorite()
    {
        abort_unless($this->photo->gallery->is_share_selectable, 403);

        $this->photo->toggleFavorite();

        if ($this->photo->gallery->isSelectionLimitReached()) {
            $this->dispatch('selection-limit-reached');

            $this->photo->gallery->notifyOwnerWhenSelectionLimitReached();
        }

        $this->dispatch('photo-favorited');
    }
}; ?>

<div class="group relative aspect-square flex overflow-hidden bg-zinc-100 dark:bg-white/10">
    <a
        id="{{ $htmlId }}"
        wire:navigate
        href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $photo, 'navigateFavorites' => $asFavorite ? true : null]) }}"
        class="mx-auto flex"
    >
        <img src="{{ $photo->small_thumbnail_url }}" alt="" @contextmenu.prevent class="object-cover" />
@if ($photo->gallery->is_share_watermarked && $photo->gallery->team->brand_watermark_url)
    @if ($photo->gallery->team->brand_watermark_position === 'repeated')
        <div class="absolute inset-0 pointer-events-none"
            style="background-image: url('{{ $photo->gallery->team->brand_watermark_url }}'); background-repeat: repeat; background-size: 32px 32px; opacity: {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }};"
        ></div>
    @else
        <div
            @class([
                'absolute flex justify-center',
                'inset-x-0 bottom-0' => $photo->gallery->team->brand_watermark_position === 'bottom',
                'inset-x-0 top-0' => $photo->gallery->team->brand_watermark_position === 'top',
                'inset-0 flex items-center' => $photo->gallery->team->brand_watermark_position === 'middle',
            ])
        >
            <img class="h-5" src="{{ $photo->gallery->team->brand_watermark_url }}" alt="" style="opacity: {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }}" loading="lazy" />
        </div>
    @endif
@endif
    </a>
    <div class="absolute right-1.5 bottom-1.5 hidden gap-2 group-hover:flex">
        @if ($photo->gallery->is_share_selectable)
            <flux:button wire:click="favorite({{ $photo->id }})" square size="sm">
                @if ($photo->isFavorited())
                    <flux:icon.heart class="size-5 text-red-500" variant="solid" />
                @else
                    <flux:icon.heart class="size-5" />
                @endif
            </flux:button>
        @endif

        @if ($photo->gallery->is_share_downloadable)
            <flux:button
                :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                icon="arrow-down-tray"
                square
                size="sm"
            />
        @endif
    </div>
</div>
