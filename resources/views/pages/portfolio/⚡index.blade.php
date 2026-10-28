<?php

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

    public ?Collection $galleries;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        abort_if($this->team->portfolio_public_disabled, 404);

        $this->galleries = $this->team->galleries()->public()->with('photos')->get();
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name.' - Portfolio');
    }
}; ?>

<x-slot name="font">{{ $team->brand_font }}</x-slot>

<x-slot name="color">{{ $team->brand_color }}</x-slot>

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

        @if($galleries->isNotEmpty())
            <div class="my-14">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($galleries as $gallery)
                        <x-ts-card class="group relative overflow-hidden hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors p-0!">
                            <a
                                wire:navigate
                                href="{{ route('portfolio.show', ['handle' => $team->handle, 'gallery' => $gallery]) }}"
                                class="block"
                            >
                                @if($gallery->coverPhoto)
                                    <img
                                        src="{{ $gallery->coverPhoto->small_thumbnail_url }}"
                                        alt="{{ $gallery->name }}"
                                        class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105 rounded-t-lg"
                                    />
                                @elseif($gallery->photos->isNotEmpty())
                                    <img
                                        src="{{ $gallery->photos->first()->small_thumbnail_url }}"
                                        alt="{{ $gallery->name }}"
                                        class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105 rounded-t-lg"
                                    />
                                @else
                                    <div class="w-full h-48 bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center rounded-t-lg">
                                        <x-icon name="photo" class="size-12 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                @endif

                                <div class="p-4">
                                    <x-ts-heading size="lg" class="mb-2">
                                        {{ $gallery->name }}
                                    </x-ts-heading>

                                    <div class="flex items-center justify-between">
                                        <x-ts-text variant="subtle" size="sm">
                                            {{ $gallery->photos->count() }} {{ __('photos') }}
                                        </x-ts-text>

                                        @if($gallery->created_at)
                                            <x-ts-text variant="subtle" size="sm">
                                                {{ $gallery->created_at->format('M j, Y') }}
                                            </x-ts-text>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </x-ts-card>
                    @endforeach
                </div>
            </div>
        @endif

        @include('partials.social-links')

        @unlesssubscribed($team)
            <div class="mt-10">
                @include('partials.powered-by')
            </div>
        @endsubscribed
    </div>
</div>
