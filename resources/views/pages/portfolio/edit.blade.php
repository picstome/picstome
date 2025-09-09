<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('portfolio.edit');

middleware(['auth', 'verified']);

new class extends Component
{
    public function addToPortfolio(Gallery $gallery)
    {
        $this->authorize('update', $gallery);

        $gallery->makePublic();
    }

    public function removeFromPortfolio(Gallery $gallery)
    {
        $this->authorize('update', $gallery);

        $gallery->makePrivate();
    }

    public function reorderGallery($gallery, $newOrder)
    {
        $this->authorize('update', $gallery);

        // Get all portfolio galleries for this team
        $portfolioGalleries = Gallery::where('team_id', $gallery->team_id)
            ->where('is_public', true)
            ->orderBy('portfolio_order')
            ->get();

        // Find current position
        $currentIndex = $portfolioGalleries->search(function ($g) use ($gallery) {
            return $g->id === $gallery->id;
        });

        if ($currentIndex === false) {
            return; // Gallery not found in portfolio
        }

        $newIndex = $newOrder - 1; // Convert to 0-based index

        // Remove the gallery from its current position
        $portfolioGalleries->splice($currentIndex, 1);

        // Insert at new position
        $portfolioGalleries->splice($newIndex, 0, [$gallery]);

        // Update order for all galleries
        foreach ($portfolioGalleries as $index => $gal) {
            $gal->update(['portfolio_order' => $index + 1]);
        }
    }

    #[Computed]
    public function currentTeam()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function portfolioGalleries()
    {
        return Gallery::where('team_id', $this->currentTeam?->id)
            ->public()
            ->get();
    }

    #[Computed]
    public function availableGalleries()
    {
        return Gallery::where('team_id', $this->currentTeam?->id)
            ->private()
            ->latest()
            ->get();
    }
}; ?>

<x-app-layout>
    @volt('pages.portfolio.edit')
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Portfolio') }}</x-heading>
                    <x-subheading>{{ __('Manage your public portfolio galleries.') }}</x-subheading>
                </div>
            </div>

            <div class="mt-12 space-y-8">
                <!-- Portfolio Galleries -->
                <div>
                    <x-heading level="2" size="lg">{{ __('Portfolio Galleries') }}</x-heading>
                    <x-subheading>{{ __('These galleries are publicly visible on your portfolio page.') }}</x-subheading>

                    @if ($this->portfolioGalleries?->isNotEmpty())
                        <div class="mt-6 space-y-4">
                            @foreach ($this->portfolioGalleries as $gallery)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="flex items-center gap-4">
                                        <img
                                            src="{{ $gallery->photos()->first()?->thumbnail_url }}"
                                            alt=""
                                            class="size-16 rounded-lg object-cover"
                                        />
                                        <div>
                                            <flux:heading>{{ $gallery->name }}</flux:heading>
                                            <flux:text>{{ $gallery->photos()->count() }} photos</flux:text>
                                        </div>
                                    </div>
                                    <flux:button wire:click="removeFromPortfolio({{ $gallery->id }})" variant="danger" size="sm">
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
                            <flux:text>{{ __('No galleries in your portfolio yet.') }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Available Galleries -->
                <div>
                    <x-heading level="2" size="lg">{{ __('Available Galleries') }}</x-heading>
                    <x-subheading>{{ __('Add galleries to your portfolio to make them publicly visible.') }}</x-subheading>

                    @if ($this->availableGalleries?->isNotEmpty())
                        <div class="mt-6 space-y-4">
                            @foreach ($this->availableGalleries as $gallery)
                                <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="flex items-center gap-4">
                                        <img
                                            src="{{ $gallery->photos()->first()?->thumbnail_url }}"
                                            alt=""
                                            class="size-16 rounded-lg object-cover"
                                        />
                                        <div>
                                            <flux:heading>{{ $gallery->name }}</flux:heading>
                                            <flux:text>{{ $gallery->photos()->count() }} photos</flux:text>
                                        </div>
                                    </div>
                                    <flux:button wire:click="addToPortfolio({{ $gallery->id }})" variant="primary" size="sm">
                                        {{ __('Add to Portfolio') }}
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-6 rounded-lg border border-zinc-200 bg-zinc-50 p-8 text-center dark:border-zinc-700 dark:bg-zinc-900">
                            <flux:text>{{ __('All your galleries are already in your portfolio.') }}</flux:text>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endvolt
</x-app-layout>