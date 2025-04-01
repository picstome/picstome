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

    #[Url]
    public $activeTab = 'all';

    public function mount(Gallery $gallery)
    {
        $this->getFavorites();
    }

    #[On('photo-favorited')]
    public function getFavorites()
    {
        $this->favorites = $this->gallery->photos()->favorited()->orderBy('name')->with('gallery')->get();
    }

    public function with()
    {
        return [
            'allPhotos' => $this->gallery->photos()->orderBy('name')->with('gallery')->get(),
        ];
    }
}; ?>

<x-guest-layout :font="$gallery->team->brand_font" :color="$gallery->team->brand_color">
    @volt('pages.shares.show')
        <div
            x-data
            x-on:selection-limit-reached.window="alert('{{ __('You have reached the limit for photo selection.') }}')"
        >
            <div>
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-9" />
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $gallery->name }}</x-heading>
                    </div>
                </div>
                <div class="flex gap-4">
                    @if ($this->gallery->is_share_downloadable)
                        <flux:button :href="route('shares.download', ['gallery' => $gallery])" variant="primary">
                            {{ __('Download') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($allPhotos->isNotEmpty())
                <div class="mt-8">
                    <flux:navbar class="border-b border-zinc-800/10 dark:border-white/20">
                        <flux:navbar.item
                            @click="$wire.activeTab = 'all'"
                            x-bind:data-current="$wire.activeTab === 'all'"
                        >
                            {{ __('All photos') }}
                        </flux:navbar.item>
                        <flux:navbar.item
                            @click="$wire.activeTab = 'favorited'"
                            x-bind:data-current="$wire.activeTab === 'favorited'"
                        >
                            {{ __('Favorited') }}
                        </flux:navbar.item>
                    </flux:navbar>

                    <div x-show="$wire.activeTab === 'all'" class="pt-8">
                        <div
                            class="grid grid-flow-dense auto-rows-[155px] grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-x-4 gap-y-6"
                        >
                            @foreach ($allPhotos as $photo)
                                <livewire:shared-photo-item :$photo :key="'photo-'.$photo->id" lazy />
                            @endforeach
                        </div>
                    </div>

                    <div x-show="$wire.activeTab === 'favorited'" class="pt-8">
                        <div
                            class="grid grid-flow-dense auto-rows-[155px] grid-cols-[repeat(auto-fill,minmax(200px,1fr))] gap-4"
                        >
                            @foreach ($favorites as $photo)
                                <livewire:shared-photo-item
                                    :$photo
                                    :asFavorite="true"
                                    :key="'favorite-'.$photo->id"
                                    lazy
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
        </div>
    @endvolt
</x-guest-layout>
