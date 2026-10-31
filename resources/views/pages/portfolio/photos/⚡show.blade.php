<?php

use App\Models\Photo;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.guest', ['fullScreen' => true])]
class extends Component
{
    public Photo $photo;

    public ?Photo $next;

    public ?Photo $previous;

    public function mount(Photo $photo)
    {
        abort_unless($photo->gallery->is_public, 404);

        $this->photo = $photo;
        $this->next = $this->imageNeighbor(1);
        $this->previous = $this->imageNeighbor(-1);
    }

    /**
     * Find the neighbor image offset positions away, skipping non-image
     * photos the way the portfolio grid does.
     */
    protected function imageNeighbor(int $offset): ?Photo
    {
        $imageIds = $this->photo->gallery->photos()
            ->get()
            ->filter(fn ($photo) => $photo->isImage())
            ->pluck('name', 'id')
            ->sort(SORT_NATURAL)
            ->keys();

        $currentIndex = $imageIds->search($this->photo->id);

        if ($currentIndex === false) {
            return null;
        }

        $neighborId = $imageIds->get($currentIndex + $offset);

        return $neighborId === null ? null : Photo::find($neighborId);
    }

    public function rendering(View $view): void
    {
        $view->title($this->photo->gallery->name.' - Photo');
    }
}; ?>

<x-slot name="font">{{ $photo->gallery->team->brand_font }}</x-slot>

<x-slot name="color">{{ $photo->gallery->team->brand_color }}</x-slot>

<x-slot name="head">
    @if(app()->environment('production'))
        @include('partials.google-analytics')
    @endif
</x-slot>

<div
    x-data="{
        zoom: false,
        scale: 1,
        translateX: 0,
        translateY: 0,
        thumbnailUrl: '{{ $photo->thumbnail_url }}',
        photoUrl: '{{ $photo->url }}',
        navigating: false,
        gestureActive: false,
        gestureBlocked: false,
        gestureHadMultipleTouches: false,
        swipeStartX: null,
        swipeStartY: null,
        swipeStartedAt: 0,
        pinchStartDistance: 0,
        pinchStartScale: 1,
        panStartX: 0,
        panStartY: 0,
        panStartTranslateX: 0,
        panStartTranslateY: 0,
        initPhotoViewer() {
            if (this.photoUrl) {
                const image = new Image()
                image.src = this.photoUrl
            }
        },
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        onTouchStart(event) {
            if (event.target.closest('button, a, input, textarea, select, [data-no-gesture]')) {
                this.gestureBlocked = true

                return
            }

            this.gestureBlocked = false
            this.gestureActive = true

            if (event.touches.length >= 2) {
                this.gestureHadMultipleTouches = true
                this.swipeStartX = null
                this.swipeStartY = null

                const first = event.touches[0]
                const second = event.touches[1]
                this.pinchStartDistance = Math.hypot(
                    first.clientX - second.clientX,
                    first.clientY - second.clientY,
                ) || 1
                this.pinchStartScale = this.scale
                this.zoom = true

                return
            }

            if (event.touches.length === 1) {
                const touch = event.touches[0]

                if (this.scale > 1.01) {
                    this.zoom = true
                    this.panStartX = touch.clientX
                    this.panStartY = touch.clientY
                    this.panStartTranslateX = this.translateX
                    this.panStartTranslateY = this.translateY
                } else {
                    this.swipeStartX = touch.clientX
                    this.swipeStartY = touch.clientY
                    this.swipeStartedAt = Date.now()
                }
            }
        },
        onTouchMove(event) {
            if (this.gestureBlocked || ! this.gestureActive) {
                return
            }

            if (event.touches.length >= 2 && this.pinchStartDistance > 0) {
                const first = event.touches[0]
                const second = event.touches[1]
                const distance = Math.hypot(
                    first.clientX - second.clientX,
                    first.clientY - second.clientY,
                )
                this.scale = Math.min(
                    4,
                    Math.max(1, this.pinchStartScale * (distance / this.pinchStartDistance)),
                )
                this.zoom = this.scale > 1.01

                if (this.scale <= 1.01) {
                    this.translateX = 0
                    this.translateY = 0
                }

                return
            }

            if (event.touches.length === 1 && this.scale > 1.01) {
                const touch = event.touches[0]
                this.translateX = this.panStartTranslateX + (touch.clientX - this.panStartX)
                this.translateY = this.panStartTranslateY + (touch.clientY - this.panStartY)
            }
        },
        onTouchEnd(event) {
            if (this.gestureBlocked) {
                this.resetGesture()

                return
            }

            if (event.touches.length > 0) {
                if (this.scale > 1.01 && event.touches[0]) {
                    this.panStartX = event.touches[0].clientX
                    this.panStartY = event.touches[0].clientY
                    this.panStartTranslateX = this.translateX
                    this.panStartTranslateY = this.translateY
                }

                this.swipeStartX = null
                this.swipeStartY = null

                return
            }

            const wasMulti = this.gestureHadMultipleTouches
            const touch = event.changedTouches[0]
            const startX = this.scale > 1.01 ? this.panStartX : this.swipeStartX
            const startY = this.scale > 1.01 ? this.panStartY : this.swipeStartY
            const dx = touch && startX !== null ? touch.clientX - startX : 0
            const dy = touch && startY !== null ? touch.clientY - startY : 0
            const duration = Date.now() - this.swipeStartedAt

            if (this.scale > 1.01 && Math.abs(dx) < 10 && Math.abs(dy) < 10 && ! wasMulti) {
                this.resetZoom()
            }

            this.resetGesture()

            if (wasMulti || this.scale > 1.01 || this.zoom) {
                return
            }

            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5 || duration > 800) {
                return
            }

            this.navigatePhoto(dx < 0 ? 'next' : 'previous')
        },
        resetGesture() {
            this.gestureActive = false
            this.gestureBlocked = false
            this.gestureHadMultipleTouches = false
            this.swipeStartX = null
            this.swipeStartY = null
            this.swipeStartedAt = 0
            this.pinchStartDistance = 0
        },
        resetZoom() {
            this.zoom = false
            this.scale = 1
            this.translateX = 0
            this.translateY = 0
        },
        toggleZoom() {
            if (this.scale > 1.01) {
                this.resetZoom()

                return
            }

            this.zoom = true
            this.scale = 2
        },
        navigatePhoto(direction) {
            if (this.navigating) {
                return
            }

            const ref = direction === 'next' ? this.$refs.next : this.$refs.previous

            if (! ref) {
                return
            }

            this.navigating = true
            Livewire.navigate(ref.href)
            setTimeout(() => { this.navigating = false }, 500)
        },
    }"
    x-init="initPhotoViewer()"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    class="flex h-screen flex-col"
>
    <div
        id="photo"
        class="relative flex h-full flex-1 overflow-hidden"
        style="touch-action: none;"
        @touchstart="onTouchStart($event)"
        @touchmove.prevent="onTouchMove($event)"
        @touchend="onTouchEnd($event)"
        @touchcancel="onTouchEnd($event)"
    >
        @if ($photo->isImage())
            <img
                x-show="!zoom"
                src="{{ $photo->thumbnail_url }}"
                srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                sizes="(max-width: 640px) 100vw, 80vw"
                style="background-image: url('{{ $photo->small_thumbnail_url }}'); background-size: cover; background-repeat: no-repeat; background-position: center;"
                @click="if (!isMobile()) toggleZoom()"
                @contextmenu.prevent
                class="mx-auto max-w-full animate-pulse bg-black/60 object-contain h-full w-full hover:cursor-zoom-in dark:bg-white/60"
                onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full'); this.style.backgroundImage = 'none'"
                onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
                alt="{{ $photo->name }}"
            />

            <img
                x-show="zoom"
                src="{{ $photo->url }}"
                style="background-image: url('{{ $photo->small_thumbnail_url }}'); background-size: cover; background-repeat: no-repeat; background-position: center;"
                x-ref="fullImage"
                x-bind:style="{ transform: 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + scale + ')' }"
                @click="if (!isMobile()) resetZoom()"
                @contextmenu.prevent
                class="mx-auto max-h-full max-w-full animate-pulse bg-black/60 object-contain h-full w-full hover:cursor-zoom-out dark:bg-white/60"
                onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full'); this.style.backgroundImage = 'none'"
                onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
                alt="{{ $photo->name }}"
                x-cloak
                data-full-image
            />
        @else
            <div class="flex h-full w-full flex-col items-center justify-center gap-4 px-8 text-center">
                <x-icon name="document-text" class="size-16 text-zinc-400 dark:text-white/50" />
                <x-ts-text class="max-w-full truncate">{{ $photo->name }}</x-ts-text>
            </div>
        @endif

        <div class="absolute top-0 bottom-0 left-0 items-center max-sm:top-auto max-sm:py-1 flex px-3 max-sm:px-1"
            :class="zoom ? 'hidden' : 'flex'">
            @if ($previous)
                <x-ts-button
                    href="{{ route('portfolio.photos.show', ['handle' => $photo->gallery->team->handle, 'gallery' => $photo->gallery, 'photo' => $previous]) }}"
                    wire:navigate
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
                <x-ts-button
                    href="{{ route('portfolio.photos.show', ['handle' => $photo->gallery->team->handle, 'gallery' => $photo->gallery, 'photo' => $next]) }}"
                    wire:navigate
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
                <x-ts-button
                    href="{{ route('portfolio.show', ['handle' => $photo->gallery->team->handle, 'gallery' => $photo->gallery]) }}"
                    wire:navigate
                    icon="arrow-left"
                    size="sm"
                    icon:variant="micro"
                >
                    {{ __('Back') }}
                </x-ts-button>
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
@endassets

@push('head')
    @if ($photo->isImage())
        <link rel="preload" as="image" href="{{ $photo->url }}">
    @endif

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
