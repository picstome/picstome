<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

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
        thumbnailUrl: '{{ $photo->thumbnail_url }}',
        photoUrl: '{{ $photo->url }}',
    }"
    x-init="new Hammer($el).on('swipeleft swiperight', function(ev) {$dispatch(ev.type)})"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    @swipeleft="$refs.next && Livewire.navigate($refs.next.href)"
    @swiperight="$refs.previous && Livewire.navigate($refs.previous.href)"
    class="flex h-screen flex-col"
>
    <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
<img
             :src="zoom ? photoUrl : thumbnailUrl"
             :srcset="!zoom ? `${thumbnailUrl} 1000w, {{ $photo->large_thumbnail_url }} 2040w` : false"
             :sizes="!zoom ? '(max-width: 640px) 100vw, 80vw' : false"
             @contextmenu.prevent
             @click="zoom = !zoom"
             class="mx-auto object-contain"
             :class="zoom ? 'max-w-none hover:cursor-zoom-out' : 'max-w-full hover:cursor-zoom-in'"
             alt=""
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
    @endif
    @if ($previous)
        <link rel="preload" as="image" href="{{ $previous->url }}">
        <link rel="preload" as="image" href="{{ $previous->thumbnail_url }}">
    @endif
@endpush
