<?php

use App\Models\Photo;
use Livewire\Volt\Component;

new class extends Component
{
    public Photo $photo;

    public ?string $htmlId = null;

    public $asFavorite = false;

    public $asCommented = false;

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

<div
    class="group relative flex aspect-square overflow-hidden bg-zinc-100 dark:bg-white/10"
    @if (!$photo->small_thumbnail_url) wire:poll.visible.5s @endif
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
            <video class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10" muted>
                <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                Your browser does not support the video tag.
            </video>
        @else
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
        @endif

        @if ($photo->gallery->is_share_watermarked && $photo->gallery->team->brand_watermark_url)
            @if ($photo->gallery->team->brand_watermark_position === 'repeated')
                <div
                    class="pointer-events-none absolute inset-0"
                    style="
                        background-image: url('{{ $photo->gallery->team->brand_watermark_url }}');
                        background-repeat: repeat;
                        background-size: 75%;
                        opacity: {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }};
                    "
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
                    <img
                        class="max-w-full"
                        src="{{ $photo->gallery->team->brand_watermark_url }}"
                        alt=""
                        style="
                            opacity: {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }};
                        "
                        loading="lazy"
                    />
                </div>
            @endif
        @endif
    </a>
    @if (($photo->comments_count ?? 0) > 0)
        <div class="absolute top-1.5 left-1.5 z-10" :class="showActions ? 'flex' : 'hidden'">
            <flux:badge icon="chat-bubble-left" size="sm" color="zinc" variant="solid">
                {{ $photo->comments_count }}
            </flux:badge>
        </div>
    @endif

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
