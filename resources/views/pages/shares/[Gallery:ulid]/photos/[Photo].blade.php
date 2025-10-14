<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Photo;
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
            ->when($this->navigateFavorites, fn ($str) => $str->append('?activeTab=favorited'))
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
                watermarkTransparency: {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }},
                isMobile() {
                    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                        navigator.userAgent,
                    )
                },
                imgWidth: 0,
                imgHeight: 0,
                containerWidth: 0,
                containerHeight: 0,
                watermarkStyle: '',
                repeatedWatermarkStyle: '',
                showWatermark: false,
                updateDimensions() {
                    const img = this.$refs.photoImg
                    const container = img ? img.parentElement : null
                    if (img && container) {
                        // Use naturalWidth/naturalHeight for aspect ratio
                        const naturalWidth = img.naturalWidth
                        const naturalHeight = img.naturalHeight
                        const containerWidth = container.offsetWidth
                        const containerHeight = container.offsetHeight
                        this.containerWidth = containerWidth
                        this.containerHeight = containerHeight
                        let renderedWidth, renderedHeight
                        const imgAspect = naturalWidth / naturalHeight
                        const containerAspect = containerWidth / containerHeight
                        if (imgAspect > containerAspect) {
                            renderedWidth = containerWidth
                            renderedHeight = containerWidth / imgAspect
                        } else {
                            renderedHeight = containerHeight
                            renderedWidth = containerHeight * imgAspect
                        }
                        // Watermark position logic
                        const pos = '{{ $photo->gallery->team->brand_watermark_position }}';
                        let style = '';
                        if (pos === 'top') {
                            style = `left: ${(containerWidth - renderedWidth) / 2 + renderedWidth/2 - 16}px; top: ${(containerHeight - renderedHeight) / 2 + 8}px;`;
                        } else if (pos === 'bottom') {
                            style = `left: ${(containerWidth - renderedWidth) / 2 + renderedWidth/2 - 16}px; top: ${(containerHeight - renderedHeight) / 2 + renderedHeight - 40}px;`;
                        } else if (pos === 'middle') {
                            style = `left: ${(containerWidth - renderedWidth) / 2 + renderedWidth/2 - 16}px; top: ${(containerHeight - renderedHeight) / 2 + renderedHeight/2 - 16}px;`;
                        } else if (pos === 'repeated') {
                            // Tile watermark as background
                            const url = '{{ $photo->gallery->team->brand_watermark_url }}';
                            this.repeatedWatermarkStyle = `
                                left: ${(containerWidth - renderedWidth) / 2}px;
                                top: ${(containerHeight - renderedHeight) / 2}px;
                                width: ${renderedWidth}px;
                                height: ${renderedHeight}px;
                                background-image: url('${url}');
                                background-repeat: repeat;
                                background-size: 64px 64px;
                                opacity: ${this.watermarkTransparency};
                                pointer-events: none;
                                position: absolute;
                            `;
                        }
                        this.watermarkStyle = style;
                        this.showWatermark = true;
                    } else {
                        this.showWatermark = false;
                    }
                },
            }"
            x-init="
                (() => {
                    const hammer = new Hammer($el, { touchAction: 'auto' })
                    hammer.get('pinch').set({ enable: true })
                    hammer.on('pinch panleft panright', function (ev) {
                        $dispatch(ev.type, ev)
                    })
                    updateDimensions()
                })()
            "
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
            x-on:resize.window="updateDimensions()"
        >
            <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
                <div class="relative flex h-full w-full items-center justify-center">
                    <img
                        x-show="!zoom && !pinchZooming"
                        x-ref="photoImg"
                        @load="updateDimensions"
                        src="{{ $photo->thumbnail_url }}"
                        srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                        sizes="(max-width: 640px) 100vw, 80vw"
                        @click="if (!isMobile()) zoom = true"
                        @contextmenu.prevent
                        class="mx-auto h-full w-full max-w-full animate-pulse bg-black/60 object-contain hover:cursor-zoom-in dark:bg-white/60"
                        onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60')"
                        onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60')"
                        alt="{{ $photo->name }}"
                    />
                    <img
                        x-show="!zoom && pinchZooming"
                        src="{{ $photo->url }}"
                        @click="if (!isMobile()) zoom = true"
                        class="mx-auto h-full w-full max-w-full animate-pulse bg-black/60 object-contain hover:cursor-zoom-in dark:bg-white/60"
                        onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60')"
                        onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60')"
                        alt="{{ $photo->name }}"
                        x-cloak
                    />
                    <img
                        x-show="zoom"
                        src="{{ $photo->url }}"
                        @click="zoom = false"
                        @contextmenu.prevent
                        class="mx-auto max-w-none animate-pulse bg-black/60 object-contain hover:cursor-zoom-out dark:bg-white/60"
                        onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60')"
                        onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60')"
                        loading="lazy"
                        alt="{{ $photo->name }}"
                        x-cloak
                    />
                    @if ($photo->gallery->is_share_watermarked && $photo->gallery->team->brand_watermark_url)
                        @if ($photo->gallery->team->brand_watermark_position === 'repeated')
                            <div
                                x-show="showWatermark"
                                :style="repeatedWatermarkStyle"
                                class="pointer-events-none absolute"
                            ></div>
                        @else
                            <img
                                x-show="showWatermark"
                                :style="watermarkStyle + ';opacity:' + watermarkTransparency + ';'"
                                class="pointer-events-none absolute h-8"
                                src="{{ $photo->gallery->team->brand_watermark_url }}"
                                alt=""
                            />
                        @endif
                    @endif
                </div>
                <div
                    class="absolute top-0 bottom-0 left-0 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
                    :class="zoom ? 'hidden' : 'flex'"
                >
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
                <div
                    class="absolute top-0 right-0 bottom-0 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
                    :class="zoom ? 'hidden' : 'flex'"
                >
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
                <div
                    class="absolute top-0 right-0 left-0 flex items-center justify-between gap-4 p-3 max-sm:p-1"
                    :class="zoom ? 'hidden' : 'flex'"
                >
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
                                :icon:variant="$photo->isFavorited() ? 'mini' : 'outline'"
                                size="sm"
                                @class(['text-red-500!' => $photo->isFavorited()])
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
            <link rel="preload" as="image" href="{{ $photo->url }}" />

            @if ($next)
                <link rel="prefetch" as="image" href="{{ $next->url }}" />
                <link rel="prefetch" as="image" href="{{ $next->thumbnail_url }}" />
                <link rel="prefetch" as="image" href="{{ $next->large_thumbnail_url }}" />
            @endif

            @if ($previous)
                <link rel="prefetch" as="image" href="{{ $previous->url }}" />
                <link rel="prefetch" as="image" href="{{ $previous->thumbnail_url }}" />
                <link rel="prefetch" as="image" href="{{ $previous->large_thumbnail_url }}" />
            @endif
        @endpush
    @endvolt
</x-guest-layout>
