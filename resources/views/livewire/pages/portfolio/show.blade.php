<?php

use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;
    public Gallery $gallery;
    public ?Collection $photos;

    public function mount(Gallery $gallery)
    {
        abort_unless($gallery->is_public, 404);

        $this->team = $gallery->team;
        $this->gallery = $gallery;
        $this->photos = $this->gallery->photos()->with('gallery')->get();
    }

    public function rendering(View $view): void
    {
        $view->title($this->gallery->name);
    }
}; ?>

<x-slot name="head">
    @if(app()->environment('production'))
        @include('partials.google-analytics')
    @endif
</x-slot>

<div class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="h-full">
        <div class="space-y-4 text-center">
            <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block space-y-4" wire:navigate>
                @if($team->brand_logo_icon_url)
                    <img src="{{ $team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $team->name }}" />
                @else
                    <flux:heading size="xl">{{ $team->name }}</flux:heading>
                @endif
            </a>
        </div>

        @if($photos->isNotEmpty())
            <div class="relative h-[164px] md:h-[240px] overflow-hidden mt-4 lg:mt-8 max-sm:-mx-6">
                <img src="{{ ($gallery->coverPhoto ?? $photos->first())->large_thumbnail_url }}" class="w-full h-full object-cover" />
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
            <div class="max-sm:w-full sm:flex-1">
                <flux:breadcrumbs class="mb-2">
                    <flux:breadcrumbs.item href="{{ route('portfolio.index', ['handle' => $team->handle]) }}">
                        {{ __('Portfolio') }}
                    </flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>
                        {{ $gallery->name }}
                    </flux:breadcrumbs.item>
                </flux:breadcrumbs>
                <div class="flex items-center gap-4">
                    <x-heading level="1" size="xl">{{ $gallery->name }}</x-heading>
                </div>

                @if($gallery->share_description)
                    <x-subheading class="mt-2">
                        {{ $gallery->share_description }}
                    </x-subheading>
                @endif
            </div>
        </div>

        @if ($photos->isNotEmpty())
            <div class="mt-8 max-sm:-mx-5">
                <div class="pt-1">
                    <div
                        class="grid grid-flow-dense grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1"
                    >
                        @foreach ($photos as $photo)
                            <div class="group relative aspect-square flex overflow-hidden bg-zinc-100 dark:bg-white/10">
                                <a
                                    id="photo-{{ $photo->id }}"
                                    wire:navigate
                                    href="{{ route('portfolio.photos.show', ['handle' => $team->handle, 'gallery' => $gallery, 'photo' => $photo]) }}"
                                    class="mx-auto flex w-full"
                                >
                                    <img
                                        x-data="{ loaded: false, errored: false }"
                                        x-init="if ($el.complete) loaded = true"
                                        src="{{ $photo->small_thumbnail_url }}"
                                        alt=""
                                        @contextmenu.prevent
                                        x-on:load="loaded = true"
                                        x-on:error="errored = true"
                                        class="object-cover"
                                        :class="loaded || errored ? 'object-cover' : 'object-cover animate-pulse bg-zinc-300 dark:bg-white/10 h-full w-full'"
                                        loading="lazy" />
                                </a>
                            </div>
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

        @include('partials.social-links')

        @unlesssubscribed($gallery->team)
            <div class="mt-10">
                @include('partials.powered-by')
            </div>
        @endsubscribed
    </div>
</div>
