<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('shares.show');

middleware([PasswordProtectGallery::class]);

render(function (View $view, Gallery $gallery) {
    abort_unless($gallery->is_shared, 404);
});

new class extends Component
{
    public Gallery $gallery;

    public Collection $favorites;

    public Collection $commentedPhotos;

    #[Url]
    public $activeTab = 'all';

    public function mount(Gallery $gallery)
    {
        $this->getFavorites();
        $this->getCommentedPhotos();
    }

    #[On('photo-favorited')]
    public function getFavorites()
    {
        $favorites = $this->gallery->photos()->favorited()->with('gallery')->withCount('comments')->get();

        $this->favorites = $favorites->naturalSortBy('name');
    }

    public function getCommentedPhotos()
    {
        $commented = $this->gallery->photos()
            ->whereHas('comments')
            ->with('gallery')
            ->withCount('comments')
            ->get();

        $this->commentedPhotos = $commented->naturalSortBy('name');
    }

    public function with()
    {
        $photos = $this->gallery->photos()->with('gallery')->withCount('comments')->get();

        return [
            'allPhotos' => $photos->naturalSortBy('name'),
            'commentedPhotos' => $this->commentedPhotos ?? collect(),
        ];
    }
}; ?>

<x-guest-layout :font="$gallery->team->brand_font" :color="$gallery->team->brand_color">
    @volt('pages.shares.show')
        <div
            x-data
            x-on:selection-limit-reached.window="alert('{{ __('You have reached the limit for photo selection.') }}')"
            class="h-full"
        >
            @if ($allPhotos->isNotEmpty())
                <div class="relative">
                    <a href="{{ route('handle.show', ['handle' => $gallery->team->handle]) }}">
                        <img
                            src="{{ $gallery->team->brand_logo_url }}"
                            class="mx-auto max-h-[90px] md:max-h-[160px]"
                        />
                    </a>
                </div>

                <div class="relative mt-4 h-[164px] overflow-hidden max-sm:-mx-6 md:h-[240px] lg:mt-8">
                    <img
                        src="{{ ($gallery->coverPhoto ?? $allPhotos->first())->large_thumbnail_url }}"
                        class="h-full w-full object-cover"
                    />
                </div>
            @else
                <div>
                    <a href="{{ route('handle.show', ['handle' => $gallery->team->handle]) }}">
                        <img
                            src="{{ $gallery->team->brand_logo_url }}"
                            class="mx-auto max-h-[90px] md:max-h-[160px]"
                        />
                    </a>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $gallery->name }}</x-heading>
                    </div>
                    @if ($gallery->share_description)
                        <x-subheading class="mt-2">
                            {{ $gallery->share_description }}
                        </x-subheading>
                    @endif
                </div>
                <div class="flex gap-4">
                    @if ($this->gallery->is_share_downloadable)
                        <flux:button
                            x-show="$wire.activeTab !== 'favorited'"
                            :href="route('shares.download', ['gallery' => $gallery])"
                            variant="primary"
                        >
                            {{ __('Download') }}
                        </flux:button>

                        <flux:button
                            x-show="$wire.activeTab === 'favorited'"
                            :href="route('shares.download', ['gallery' => $gallery, 'favorites' => true])"
                            variant="primary"
                            x-cloak
                        >
                            {{ __('Download') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($allPhotos->isNotEmpty())
                <div class="mt-8 max-sm:-mx-5">
                    <flux:navbar class="border-b border-zinc-800/10 dark:border-white/20">
                        <flux:navbar.item
                            @click="$wire.activeTab = 'all'"
                            x-bind:data-current="$wire.activeTab === 'all'"
                        >
                            {{ __('All photos') }}
                        </flux:navbar.item>

                        @if ($gallery->is_share_selectable)
                            <flux:navbar.item
                                @click="$wire.activeTab = 'favorited'"
                                x-bind:data-current="$wire.activeTab === 'favorited'"
                            >
                                {{ __('Favorited') }}
                            </flux:navbar.item>
                        @endif

                        @if ($gallery->are_comments_enabled)
                            <flux:navbar.item
                                @click="$wire.activeTab = 'commented'"
                                x-bind:data-current="$wire.activeTab === 'commented'"
                            >
                                {{ __('Commented') }}
                            </flux:navbar.item>
                        @endif
                    </flux:navbar>

                    <div x-show="$wire.activeTab === 'all'" class="pt-1">
                        <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                            @foreach ($allPhotos as $photo)
                                <livewire:shared-photo-item
                                    :$photo
                                    :key="'photo-'.$photo->id"
                                    :html-id="'photo-'.$photo->id"
                                />
                            @endforeach
                        </div>
                    </div>

                    <div x-show="$wire.activeTab === 'commented'" class="pt-1">
                        <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                            @foreach ($commentedPhotos as $photo)
                                <livewire:shared-photo-item
                                    :$photo
                                    :asCommented="true"
                                    :key="'commented-'.$photo->id"
                                    :html-id="'commented-'.$photo->id"
                                />
                            @endforeach
                        </div>
                    </div>

                    <div x-show="$wire.activeTab === 'favorited'" class="pt-1">
                        <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                            @foreach ($favorites as $photo)
                                <livewire:shared-photo-item
                                    :$photo
                                    :asFavorite="true"
                                    :key="'favorite-'.$photo->id"
                                    :html-id="'favorite-'.$photo->id"
                                />
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any photos.') }}
                    </flux:subheading>
                </div>
            @endif
            @unlesssubscribed($gallery->team)
                <div class="mt-10">
                    @include('partials.powered-by')
                </div>
            @endsubscribed
        </div>
        @script
            <script>
                document.addEventListener('livewire:navigated', () => {
                    const hash = window.location.hash;
                    if (hash) {
                        setTimeout(() => {
                            const element = document.querySelector(hash);
                            if (element) {
                                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }, 500);
                    }
                });
            </script>
        @endscript
    @endvolt
</x-guest-layout>
