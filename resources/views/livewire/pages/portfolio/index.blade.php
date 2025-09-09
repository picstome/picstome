<?php

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
    public ?Collection $galleries;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();
        $this->galleries = $this->team->galleries()->where('is_public', true)->with('photos')->get();
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name . ' - Portfolio');
    }
}; ?>

<div class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="h-full">
        @if($galleries->isNotEmpty())
            <div class="relative">
                <img src="{{ $team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $team->name }}</x-heading>
                    </div>

                    @if($team->bio)
                        <x-subheading class="mt-2">
                            {{ $team->bio }}
                        </x-subheading>
                    @endif
                </div>
            </div>

            <div class="mt-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($galleries as $gallery)
                        <div class="group relative overflow-hidden rounded-lg bg-zinc-100 dark:bg-white/10">
                            <a
                                wire:navigate.hover
                                href="{{ route('portfolio.show', ['handle' => $team->handle, 'gallery' => $gallery]) }}"
                                class="block"
                            >
                                @if($gallery->coverPhoto)
                                    <img
                                        src="{{ $gallery->coverPhoto->thumbnail_url }}"
                                        alt="{{ $gallery->name }}"
                                        class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
                                    />
                                @elseif($gallery->photos->isNotEmpty())
                                    <img
                                        src="{{ $gallery->photos->first()->thumbnail_url }}"
                                        alt="{{ $gallery->name }}"
                                        class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
                                    />
                                @else
                                    <div class="w-full h-48 bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center">
                                        <flux:icon.photo class="size-12 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                @endif

                                <div class="p-4">
                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white mb-2">
                                        {{ $gallery->name }}
                                    </h3>

                                    @if($gallery->share_description)
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">
                                            {{ $gallery->share_description }}
                                        </p>
                                    @endif

                                    <div class="mt-3 flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
                                        <span>{{ $gallery->photos->count() }} photos</span>
                                        @if($gallery->created_at)
                                            <span>{{ $gallery->created_at->format('M j, Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="relative">
                <img src="{{ $team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </div>

            <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                <flux:heading size="lg" level="2">{{ __('No public galleries') }}</flux:heading>
                <flux:subheading class="mb-6 max-w-72 text-center">
                    {{ __('This portfolio doesn\'t have any public galleries yet.') }}
                </flux:subheading>
            </div>
        @endif

        @unlesssubscribed($team)
            <div class="mt-10">
                @include('partials.powered-by')
            </div>
        @endsubscribed
    </div>
</div>