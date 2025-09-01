<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('shares.photos.show');

middleware([PasswordProtectGallery::class]);

render(function (View $view, Photo $photo) {
    abort_unless($photo->gallery->is_shared, 404);
});

new class extends Component
{
    public Photo $photo;

    public ?Photo $next;

    public ?Photo $previous;

    #[Url]
    public $navigateFavorites = false;

    public function mount()
    {
        $this->next = $this->navigateFavorites
            ? $this->photo->nextFavorite()
            : $this->photo->next();

        $this->previous = $this->navigateFavorites
            ? $this->photo->previousFavorite()
            : $this->photo->previous();
    }

    #[Computed]
    public function cameFromGallery()
    {
        $referer = request()->header('referer');
        $galleryUrl = route('shares.show', ['gallery' => $this->photo->gallery]);

        return $referer && str_starts_with($referer, $galleryUrl);
    }

    public function favorite()
    {
        abort_unless($this->photo->gallery->is_share_selectable, 401);

        if ($this->photo->gallery->photos()->favorited()->count() === $this->photo->gallery->share_selection_limit) {
            $this->dispatch('selection-limit-reached');
        }

        $this->photo->toggleFavorite();
    }
}; ?>

<x-guest-layout :full-screen="true">
    @volt('pages.shares.photos.show')
        <div
            x-data="{
                swipe: '',
                zoom: false,
                thumbnailUrl: '{{ $photo->thumbnail_url }}',
                photoUrl: '{{ $photo->url }}',
            }"
            x-init="new Hammer($el).on('swipeleft swiperight', function(ev) {$dispatch(ev.type)})"
            x-on:selection-limit-reached.window="alert('{{ __('You have reached the limit for photo selection.') }}')"
            @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
            @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
            @swipeleft="$refs.next && Livewire.navigate($refs.next.href)"
            @swiperight="$refs.previous && Livewire.navigate($refs.previous.href)"
            @if ($this->photo->gallery->is_share_selectable)
                @keyup.window.f="$wire.favorite()"
            @endif
            class="flex h-[calc(100vh-64px)] flex-col"
        >
            <div>
                @if ($this->cameFromGallery)
                    <flux:button
                        @click="history.back()"
                        icon="chevron-left"
                        variant="subtle"
                        inset
                    >
                        {{ $photo->gallery->name }}
                    </flux:button>
                @else
                    <flux:button
                        :href="route('shares.show', ['gallery' => $photo->gallery, 'activeTab' => $navigateFavorites ? 'favorited' : null])"
                        wire:navigate.hover
                        icon="chevron-left"
                        variant="subtle"
                        inset
                    >
                        {{ $photo->gallery->name }}
                    </flux:button>
                @endif
            </div>

            <div class="mt-8 flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $photo->name }}</x-heading>
                        @if ($photo->isFavorited())
                            <flux:badge color="lime" size="sm">{{ __('Favorited') }}</flux:badge>
                        @endif
                    </div>
                </div>
                <div class="flex gap-4">
                    @if ($this->photo->gallery->is_share_downloadable)
                        <flux:button
                            :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                            icon="cloud-arrow-down"
                            variant="subtle"
                        />
                    @endif

                    @if ($this->photo->gallery->is_share_selectable)
                        <flux:button wire:click="favorite" square>
                            @if ($photo->isFavorited())
                                <flux:icon.heart class="size-5" variant="solid" />
                            @else
                                <flux:icon.heart class="size-5" />
                            @endif
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="relative mt-12 h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
                <img
                    src="{{ $photo->thumbnail_url }}"
                    @contextmenu.prevent
                    @click="zoom = !zoom"
                    class="mx-auto object-contain"
                    :class="zoom ? 'max-w-none hover:cursor-zoom-out' : 'max-w-full hover:cursor-zoom-in'"
                    :src = "zoom ? photoUrl : thumbnailUrl"
                    alt=""
                />

                @if ($photo->gallery->is_share_watermarked && $photo->gallery->team->brand_watermark_url)
                    <div
                        @class([
                            'absolute flex justify-center',
                            'inset-x-0 bottom-0' => $photo->gallery->team->brand_watermark_position === 'bottom',
                            'inset-x-0 top-0' => $photo->gallery->team->brand_watermark_position === 'top',
                            'inset-0 flex items-center' => $photo->gallery->team->brand_watermark_position === 'middle',
                        ])
                    >
                        <img class="h-8" src="{{ $photo->gallery->team->brand_watermark_url }}" alt="" style="opacity: {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }}" />
                    </div>
                @endif
            </div>

            <div class="mt-12 flex justify-between">
                <div>
                    @if ($previous)
                        <flux:button
                            href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $previous, 'navigateFavorites' => $navigateFavorites ? true : null]) }}"
                            wire:navigate.hover
                            x-ref="previous"
                        >
                            {{ __('Previous') }}
                        </flux:button>
                    @endif
                </div>
                <div>
                    @if ($next)
                        <flux:button
                            href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $next, 'navigateFavorites' => $navigateFavorites ? true : null]) }}"
                            wire:navigate.hover
                            x-ref="next"
                        >
                            {{ __('Next') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @unlesssubscribed($photo->gallery->team)
                <div class="mt-10">
                    @include('partials.powered-by')
                </div>
            @endsubscribed
        </div>

        @assets
            <script type="text/javascript" src="https://unpkg.com/hammerjs@2.0.8/hammer.min.js"></script>
        @endassets

        @push('head')
            <link rel="preload" as="image" href="{{ $photo->url }}">
            @if ($next)
                <link rel="preload" as="image" href="{{ $next->url }}">
                <link rel="preload" as="image" href="{{ $next->thumbnail_url }}">
            @endif
            @if ($previous)
                <link rel="preload" as="image" href="{{ $previous->url }}">
                <link rel="preload" as="image" href="{{ $previous->thumbnail_url }}">
            @endif
        @endpush
    @endvolt
</x-guest-layout>
