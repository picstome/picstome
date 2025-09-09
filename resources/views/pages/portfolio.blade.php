<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('portfolio');

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
        $portfolioGalleries = $this->team->galleries()
            ->public()
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
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function portfolioGalleries()
    {
        return $this->team?->galleries()
            ->public()
            ->get();
    }

    #[Computed]
    public function availableGalleries()
    {
        return $this->team?->galleries()
            ->private()
            ->latest()
            ->get();
    }
}; ?>

<x-app-layout>
    @volt('pages.portfolio')
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
                        <ul class="mt-6">
                            @foreach ($this->portfolioGalleries as $gallery)
                                <li>
                                    @if (!$loop->first)
                                        <flux:separator variant="subtle" />
                                    @endif
                                    <div class="flex items-center justify-between py-6">
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
                                    <flux:button wire:click="removeFromPortfolio({{ $gallery->id }})" variant="subtle" size="sm" square>
                                        <flux:icon.x-mark variant="mini" />
                                    </flux:button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
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
                        <ul class="mt-6">
                            @foreach ($this->availableGalleries as $gallery)
                                <li>
                                    @if (!$loop->first)
                                        <flux:separator variant="subtle" />
                                    @endif
                                    <div class="flex items-center justify-between py-6">
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
                                </li>
                            @endforeach
                        </ul>
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
