<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function favorite()
    {
        abort_unless($this->photo->gallery->is_share_selectable, 401);

        if ($this->photo->gallery->photos()->favorited()->count() === $this->photo->gallery->share_selection_limit) {
            $this->dispatch('selection-limit-reached');
        }

        $this->photo->toggleFavorite();
    }

    #[Computed]
    public function galleryUrl()
    {
        return Str::of(route('shares.show', ['gallery' => $this->photo->gallery]))
            ->when($this->navigateFavorites, fn($str) => $str->append('?activeTab=favorited'))
            ->append('#')
            ->append($this->navigateFavorites ? 'favorite-' : 'photo-')
            ->append($this->photo->id);
    }
}; ?>

<x-guest-layout :full-screen="true">
    @volt('pages.shares.photos.show')
        <div
            x-data="{
                swipe: '',
                zoom: false,
                pinchZooming: false,
                thumbnailUrl: '{{ $photo->thumbnail_url }}',
                photoUrl: '{{ $photo->url }}',
                navigating: false,
             }"
            x-init="(() => {
                const hammer = new Hammer($el, { touchAction: 'auto' });
                hammer.get('pinch').set({ enable: true });
                hammer.on('pinch panleft panright', function(ev) {
                    $dispatch(ev.type, ev);
                });
            })()"
            x-on:selection-limit-reached.window="alert('{{ __('You have reached the limit for photo selection.') }}')"
            @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
            @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
            @panleft="if (!navigating && $refs.next) { navigating = true; Livewire.navigate($refs.next.href); setTimeout(() => { navigating = false }, 500) }"
            @panright="if (!navigating && $refs.previous) { navigating = true; Livewire.navigate($refs.previous.href); setTimeout(() => { navigating = false }, 500) }"
            @pinchstart="pinchZooming = true;"
            @if ($this->photo->gallery->is_share_selectable)
                @keyup.window.f="$wire.favorite()"
            @endif
            class="flex h-screen flex-col"
        >
            <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
                <img
                    x-show="!zoom && !pinchZooming"
                    src="{{ $photo->thumbnail_url }}"
                    srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                    sizes="(max-width: 640px) 100vw, 80vw"
                    @click="zoom = true"
                    @contextmenu.prevent
                    class="mx-auto object-contain max-w-full hover:cursor-zoom-in animate-pulse bg-zinc-100 dark:bg-white/10 h-full w-full"
onload="this.classList.remove('animate-pulse','bg-zinc-100','dark:bg-white/10','h-full','w-full')"
onerror="this.classList.remove('animate-pulse','bg-zinc-100','dark:bg-white/10','h-full','w-full')"
                    alt="{{ $photo->name }}"
                />

                <img
                    x-show="!zoom && pinchZooming"
                    src="{{ $photo->url }}"
                    @click="zoom = true"
                    class="mx-auto object-contain max-w-full hover:cursor-zoom-in animate-pulse bg-zinc-100 dark:bg-white/10 h-full w-full"
onload="this.classList.remove('animate-pulse','bg-zinc-100','dark:bg-white/10','h-full','w-full')"
onerror="this.classList.remove('animate-pulse','bg-zinc-100','dark:bg-white/10','h-full','w-full')"
                    alt="{{ $photo->name }}"
                    x-cloak
                />

                <img
                    x-show="zoom"
                    src="{{ $photo->url }}"
                    @click="zoom = false"
                    @contextmenu.prevent
                    class="mx-auto object-contain max-w-none hover:cursor-zoom-out animate-pulse bg-zinc-100 dark:bg-white/10 h-full w-full"
onload="this.classList.remove('animate-pulse','bg-zinc-100','dark:bg-white/10','h-full','w-full')"
onerror="this.classList.remove('animate-pulse','bg-zinc-100','dark:bg-white/10','h-full','w-full')"
                    loading="lazy"
                    alt="{{ $photo->name }}"
                    x-cloak
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
                <div class="absolute top-0 bottom-0 left-0 items-center max-sm:top-auto max-sm:py-1 flex px-3 max-sm:px-1"
                    :class="zoom ? 'hidden' : 'flex'">
                    @if ($previous)
                        <flux:button
                            href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $previous, 'navigateFavorites' => $navigateFavorites ? true : null]) }}"
                            wire:navigate.hover
                            x-ref="previous"
                            icon="chevron-left"
                            size="sm"
                            class="py-10 max-sm:py-0"
                            square
                        />
                    @endif
                </div>
                <div class="absolute top-0 bottom-0 right-0 items-center max-sm:top-auto max-sm:py-1 flex px-3 max-sm:px-1"
                    :class="zoom ? 'hidden' : 'flex'">
                    @if ($next)
                        <flux:button
                            href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $next, 'navigateFavorites' => $navigateFavorites ? true : null]) }}"
                            wire:navigate.hover
                            x-ref="next"
                            icon="chevron-right"
                            size="sm"
                            class="py-10 max-sm:py-0"
                            square
                        />
                    @endif
                </div>
                <div class="flex items-center justify-between gap-4 absolute top-0 left-0 right-0 p-3 max-sm:p-1"
                    :class="zoom ? 'hidden' : 'flex'">
                    <div class="flex gap-3">
                        <flux:button
                            :href="$this->galleryUrl"
                            wire:navigate.hover
                            icon="arrow-left"
                            size="sm"
                            icon:variant="micro"
                        >
                            {{ __('Back') }}
                        </flux:button>
                    </div>
                    <div class="flex gap-3">
                        @if ($this->photo->gallery->is_share_downloadable)
                            <flux:button
                                :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                                icon="cloud-arrow-down"
                                icon:variant="mini"
                                size="sm"
                                square
                            />
                        @endif
                        @if ($this->photo->gallery->is_share_selectable)
                            <flux:button
                                name="favorite"
                                wire:click="favorite"
                                icon="heart"
                                :variant="$photo->isFavorited() ? 'primary' : null"
                                :icon:variant="$photo->isFavorited() ? 'mini' : 'outline'"
                                :color="$photo->isFavorited() ? 'rose' : 'lime'"
                                size="sm"
                                square
                            />
                        @endif
                    </div>
                </div>
            </div>

            @unlesssubscribed($photo->gallery->team)
                <div class="py-3">
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
                <link rel="preload" as="image" href="{{ $next->large_thumbnail_url }}">
            @endif
            @if ($previous)
                <link rel="preload" as="image" href="{{ $previous->url }}">
                <link rel="preload" as="image" href="{{ $previous->thumbnail_url }}">
                <link rel="preload" as="image" href="{{ $previous->large_thumbnail_url }}">
            @endif
        @endpush
    @endvolt
</x-guest-layout>
