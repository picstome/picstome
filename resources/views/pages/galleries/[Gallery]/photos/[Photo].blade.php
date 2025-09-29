<?php

use App\Models\Photo;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
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

    public function setAsCover()
    {
        $this->authorize('updateCover', $this->photo->gallery);

        $this->photo->gallery->setCoverPhoto($this->photo);
    }

    public function removeAsCover()
    {
        $this->authorize('updateCover', $this->photo->gallery);

        if ($this->photo->gallery->coverPhoto?->is($this->photo)) {
            $this->photo->gallery->removeCoverPhoto();
        }
    }

    #[Computed]
    public function galleryUrl()
    {
        return Str::of(route('galleries.show', ['gallery' => $this->photo->gallery]))
            ->when($this->navigateFavorites, fn($str) => $str->append('?activeTab=favorited'))
            ->append('#')
            ->append($this->navigateFavorites ? 'favorite-' : 'photo-')
            ->append($this->photo->id);
    }
}; ?>

<x-app-layout :full-screen="true">
    @volt('pages.galleries.photos.show')
        <div
            x-data="{
                swipe: '',
                zoom: false,
                thumbnailUrl: '{{ $photo->thumbnail_url }}',
                photoUrl: '{{ $photo->url }}',
            }"
            x-init="
                const hammer = new Hammer($el);
                hammer.get('pinch').set({ enable: true });
                hammer.on('swipeleft swiperight', function(ev) { $dispatch(ev.type) });
                hammer.on('pinchin', function() { zoom = true });
                hammer.on('pinchout', function() { zoom = false });
            "
            @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
            @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
            @keyup.window.f="$wire.favorite()"
            @swipeleft="$refs.next && Livewire.navigate($refs.next.href)"
            @swiperight="$refs.previous && Livewire.navigate($refs.previous.href)"
            class="flex h-screen flex-col"
        >
            <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
                <img
                    x-show="!zoom"
                    src="{{ $photo->thumbnail_url }}"
                    srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                    sizes="(max-width: 640px) 100vw, 80vw"
                    @click="zoom = true"
                    class="mx-auto object-contain max-w-full hover:cursor-zoom-in"
                    alt="{{ $photo->name }}"
                />

                <img
                    x-show="zoom"
                    src="{{ $photo->url }}"
                    @click="zoom = false"
                    class="mx-auto object-contain max-w-none hover:cursor-zoom-out"
                    loading="lazy"
                    alt="{{ $photo->name }}"
                    x-cloak
                />

                <div class="absolute top-0 bottom-0 left-0 items-center max-sm:top-auto max-sm:py-1 flex px-3 max-sm:px-1"
                    :class="zoom ? 'hidden' : 'flex'">
                    @if ($previous)
                        <flux:button
                            href="/galleries/{{ $photo->gallery->id }}/photos/{{ $previous->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : '' }}"
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
                            href="/galleries/{{ $photo->gallery->id }}/photos/{{ $next->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : '' }}"
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
                            :href="$this->galleryUrl"
                            wire:navigate
                            icon="arrow-left"
                            size="sm"
                            icon:variant="micro"
                        >
                            {{ __('Back') }}
                        </flux:button>
                    </div>
                    <div class="flex gap-3">
                        <flux:tooltip toggleable>
                            <flux:button icon="information-circle" size="sm" variant="subtle" />
                            <flux:tooltip.content class="max-w-[20rem] space-y-2">
                                <p>{{ $photo->name }}</p>
                            </flux:tooltip.content>
                        </flux:tooltip>
                        <flux:dropdown>
                            <flux:button icon="ellipsis-vertical" size="sm" variant="subtle" />
                            <flux:menu>
                                @if ($photo->gallery->coverPhoto?->is($photo))
                                    <flux:menu.item
                                        wire:click="removeAsCover"
                                        icon="x-mark"
                                    >
                                        {{ __('Remove as Cover') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item
                                        wire:click="setAsCover"
                                        icon="star"
                                    >
                                        {{ __('Set as Cover') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.item
                                    wire:click="delete"
                                    wire:confirm="{{ __('Are you sure?') }}"
                                    icon="trash"
                                    variant="danger"
                                >
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                        <flux:button
                            :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                            icon="cloud-arrow-down"
                            icon:variant="mini"
                            size="sm"
                            square
                        />
                        <flux:button
                            wire:click="favorite"
                            icon="heart"
                            :variant="$photo->isFavorited() ? 'primary' : null"
                            :icon:variant="$photo->isFavorited() ? 'mini' : 'outline'"
                            :color="$photo->isFavorited() ? 'rose' : 'lime'"
                            size="sm"
                            square
                        />
                    </div>
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
                <link rel="preload" as="image" href="{{ $next->large_thumbnail_url }}">
            @endif
            @if ($previous)
                <link rel="preload" as="image" href="{{ $previous->url }}">
                <link rel="preload" as="image" href="{{ $previous->thumbnail_url }}">
                <link rel="preload" as="image" href="{{ $previous->large_thumbnail_url }}">
            @endif
        @endpush
    @endvolt
</x-app-layout>
