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
        $this->galleries = $this->team->galleries()->public()->with('photos')->get();
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name . ' - Portfolio');
    }
}; ?>

<div class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="h-full">
        @include('partials.public-branding')

        @if($galleries->isNotEmpty())
            <div class="my-14">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($galleries as $gallery)
                        <flux:card class="group relative overflow-hidden hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors p-0!">
                            <a
                                wire:navigate
                                href="{{ route('portfolio.show', ['handle' => $team->handle, 'gallery' => $gallery]) }}"
                                class="block"
                            >
                                @if($gallery->coverPhoto)
                                    <img
                                        src="{{ $gallery->coverPhoto->thumbnail_url }}"
                                        alt="{{ $gallery->name }}"
                                        class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105 rounded-t-lg"
                                    />
                                @elseif($gallery->photos->isNotEmpty())
                                    <img
                                        src="{{ $gallery->photos->first()->thumbnail_url }}"
                                        alt="{{ $gallery->name }}"
                                        class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105 rounded-t-lg"
                                    />
                                @else
                                    <div class="w-full h-48 bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center rounded-t-lg">
                                        <flux:icon.photo class="size-12 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                @endif

                                <div class="p-4">
                                    <flux:heading size="lg" class="mb-2">
                                        {{ $gallery->name }}
                                    </flux:heading>

                                    @if($gallery->share_description)
                                        <flux:text variant="subtle" class="line-clamp-2 mb-3">
                                            {{ $gallery->share_description }}
                                        </flux:text>
                                    @endif

                                    <div class="flex items-center justify-between">
                                        <flux:text variant="subtle" size="sm">
                                            {{ $gallery->photos->count() }} photos
                                        </flux:text>
                                        @if($gallery->created_at)
                                            <flux:text variant="subtle" size="sm">
                                                {{ $gallery->created_at->format('M j, Y') }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @endif

        @unlesssubscribed($team)
            <div class="mt-10">
                @include('partials.powered-by')
            </div>
        @endsubscribed
    </div>
</div>
