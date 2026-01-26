<?php

use App\Models\Gallery;
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
        $this->next = $this->photo->next();
        $this->previous = $this->photo->previous();
    }

    public function rendering(View $view): void
    {
        $view->title($this->photo->gallery->name . ' - Photo');
    }
}; ?>

<x-slot name="head">
    @if(app()->environment('production'))
        @include('partials.google-analytics')
    @endif
</x-slot>

<div
    x-data="{
        swipe: '',
        zoom: false,
        pinchZooming: false,
        thumbnailUrl: '{{ $photo->thumbnail_url }}',
        photoUrl: '{{ $photo->url }}',
        navigating: false,
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
    }"
    x-init="(() => {
        const hammer = new Hammer($el, { touchAction: 'auto' });
        hammer.get('pinch').set({ enable: true });
        hammer.on('pinch panleft panright', function(ev) {
        $dispatch(ev.type, ev);
    });
    })()"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    @panleft="if (!navigating && $refs.next) { navigating = true; Livewire.navigate($refs.next.href); setTimeout(() => { navigating = false }, 500) }"
    @panright="if (!navigating && $refs.previous) { navigating = true; Livewire.navigate($refs.previous.href); setTimeout(() => { navigating = false }, 500) }"
    @pinchstart="pinchZooming = true;"
    class="flex h-screen flex-col"
>
    <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
        <img
            x-show="!zoom && !pinchZooming"
            src="{{ $photo->thumbnail_url }}"
            srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
            sizes="(max-width: 640px) 100vw, 80vw"
            @click="if (!isMobile()) zoom = true"
            @contextmenu.prevent
            class="mx-auto object-contain max-w-full hover:cursor-zoom-in animate-pulse bg-black/60 dark:bg-white/60 h-full w-full"
            onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
            onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
            alt="{{ $photo->name }}"
        />

        <img
            x-show="!zoom && pinchZooming"
            src="{{ $photo->url }}"
            @click="if (!isMobile()) zoom = true"
            class="mx-auto object-contain max-w-full hover:cursor-zoom-in animate-pulse bg-black/60 dark:bg-white/60 h-full w-full"
            onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
            onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
            alt="{{ $photo->name }}"
            x-cloak
        />

        <img
            x-show="zoom"
            src="{{ $photo->url }}"
            @click="zoom = false"
            @contextmenu.prevent
            class="mx-auto object-contain max-w-none hover:cursor-zoom-out animate-pulse bg-black/60 dark:bg-white/60 h-full w-full"
            onload="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
            onerror="this.classList.remove('animate-pulse','bg-black/60','dark:bg-white/60','h-full','w-full')"
            loading="lazy"
            alt="{{ $photo->name }}"
            x-cloak
        />

        <div class="absolute top-0 bottom-0 left-0 items-center max-sm:top-auto max-sm:py-1 flex px-3 max-sm:px-1"
            :class="zoom ? 'hidden' : 'flex'">
            @if ($previous)
                <flux:button
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
                <flux:button
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
                <flux:button
                    href="{{ route('portfolio.show', ['handle' => $photo->gallery->team->handle, 'gallery' => $photo->gallery]) }}"
                    wire:navigate
                    icon="arrow-left"
                    size="sm"
                    icon:variant="micro"
                >
                    {{ __('Back') }}
                </flux:button>
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
