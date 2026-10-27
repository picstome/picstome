<?php

use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

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
        $this->photos = $this->gallery->photos()->with('gallery')->get()->filter(fn ($photo) => $photo->isImage())->values();
    }

    public function rendering(View $view): void
    {
        $view->title($this->gallery->name);
    }
}; ?>

<x-slot name="font">{{ $team->brand_font }}</x-slot>

<x-slot name="color">{{ $team->brand_color }}</x-slot>

<x-slot name="head">
    @if(app()->environment('production'))
        @include('partials.google-analytics')
    @endif
</x-slot>

<div class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="h-full">
        <div class="space-y-4 text-center">
            <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block space-y-4" wire:navigate>
                @if($team->brand_logo_url)
                    <img src="{{ $team->brand_logo_url }}" class="mx-auto max-h-[90px] w-auto md:max-h-[160px]" alt="{{ $team->name }}" />
                @elseif($team->brand_logo_icon_url)
                    <img src="{{ $team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $team->name }}" />
                @else
                    <x-ts-heading size="xl">{{ $team->name }}</x-ts-heading>
                @endif
            </a>

            <div class="bg-(--color-accent) mx-auto mt-3 h-1 w-10 rounded-full"></div>
        </div>

        @if($photos->isNotEmpty())
            <div class="relative h-[164px] md:h-[240px] overflow-hidden mt-4 lg:mt-8">
                <img src="{{ ($gallery->coverPhoto ?? $photos->first())->large_thumbnail_url }}" class="w-full h-full object-cover" />
            </div>
        @endif

        <div class="mt-6 lg:mt-8">
            <div class="min-w-0">
                <x-ts-breadcrumbs class="mb-2">
                    <x-ts-breadcrumbs-item href="{{ route('portfolio.index', ['handle' => $team->handle]) }}">
                        {{ __('Portfolio') }}
                    </x-ts-breadcrumbs-item>
                    <x-ts-breadcrumbs-item>
                        {{ $gallery->name }}
                    </x-ts-breadcrumbs-item>
                </x-ts-breadcrumbs>
                <div class="flex items-center gap-4">
                    <x-ts-heading level="1" size="xl">{{ $gallery->name }}</x-ts-heading>
                </div>

                @if($gallery->share_description)
                    <x-ts-subheading class="mt-2">
                        {{ $gallery->share_description }}
                    </x-ts-subheading>
                @endif
            </div>
        </div>

        @if ($photos->isNotEmpty())
            <div class="mt-8">
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
                                        class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10"
                                        :class="loaded || errored ? '' : 'animate-pulse'"
                                        loading="lazy" />
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                <x-ts-heading size="lg" level="2">{{ __('No photos') }}</x-ts-heading>
                <x-ts-subheading class="mb-6 max-w-72 text-center">
                    {{ __('We couldn’t find any photos.') }}
                </x-ts-subheading>
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
