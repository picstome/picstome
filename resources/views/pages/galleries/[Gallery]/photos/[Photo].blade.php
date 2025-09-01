<?php

use App\Models\Photo;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;

middleware(['auth', 'verified', 'can:view,photo']);

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
        $this->photo->toggleFavorite();
    }

    public function delete()
    {
        $gallery = $this->photo->gallery;

        $this->photo->deleteFromDisk()->delete();

        return $this->redirect("/galleries/{$gallery->id}");
    }
}; ?>

<x-app-layout :full-screen="true">
    @volt('pages.galleries.photos.show')
        <div
            id="photo-{{ $photo->id }}"
            x-data="{
                swipe: '',
                zoom: false,
                thumbnailUrl: '{{ $photo->thumbnail_url }}',
                photoUrl: '{{ $photo->url }}',
            }"
            x-init="new Hammer($el).on('swipeleft swiperight', function(ev) {$dispatch(ev.type)})"
            @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
            @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
            @keyup.window.f="$wire.favorite()"
            @swipeleft="$refs.next && Livewire.navigate($refs.next.href)"
            @swiperight="$refs.previous && Livewire.navigate($refs.previous.href)"
            class="flex h-[calc(100vh-64px)] flex-col"
        >
            <div>
                <flux:button
                    :href="route('galleries.show', ['gallery' => $photo->gallery, 'activeTab' => $navigateFavorites ? 'favorited' : null]) . '#photo-' . $photo->id"
                    wire:navigate.hover
                    icon="chevron-left"
                    variant="subtle"
                    inset
                >
                    {{ $photo->gallery->name }}
                </flux:button>
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
                    <flux:button
                        wire:click="delete"
                        icon="trash"
                        variant="subtle"
                        wire:confirm="{{ __('Are you sure?') }}"
                    ></flux:button>
                    <flux:button
                        :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                        icon="cloud-arrow-down"
                        variant="subtle"
                    />
                    <flux:button wire:click="favorite" square>
                        @if ($photo->isFavorited())
                            <flux:icon.heart class="size-5" variant="solid" />
                        @else
                            <flux:icon.heart class="size-5" />
                        @endif
                    </flux:button>
                </div>
            </div>

            <div class="relative mt-12 h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
                <img
                    src="{{ $photo->thumbnail_url }}"
                    @click="zoom = !zoom"
                    class="mx-auto object-contain"
                    :class="zoom ? 'max-w-none hover:cursor-zoom-out' : 'max-w-full hover:cursor-zoom-in'"
                    :src = "zoom ? photoUrl : thumbnailUrl"
                    alt=""
                />
            </div>

            <div class="mt-12 flex justify-between">
                <div>
                    @if ($previous)
                        <flux:button
                            href="/galleries/{{ $photo->gallery->id }}/photos/{{ $previous->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : '' }}#photo-{{ $previous->id }}"
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
                            href="/galleries/{{ $photo->gallery->id }}/photos/{{ $next->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : '' }}#photo-{{ $next->id }}"
                            wire:navigate.hover
                            x-ref="next"
                        >
                            {{ __('Next') }}
                        </flux:button>
                    @endif
                </div>
            </div>
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
</x-app-layout>
