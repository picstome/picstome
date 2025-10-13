<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('portfolio');

middleware(['auth', 'verified']);

new class extends Component
{
    use WithPagination;
    public function addToPortfolio(Gallery $gallery)
    {
        $this->authorize('update', $gallery);

        $gallery->makePublic();
    }

    public function disablePortfolioPage()
    {
        $this->team->update(['portfolio_public_disabled' => true]);
    }

    public function enablePortfolioPage()
    {
        $this->team->update(['portfolio_public_disabled' => false]);
    }

    public function removeFromPortfolio(Gallery $gallery)
    {
        $this->authorize('update', $gallery);

        $gallery->makePrivate();
    }

    public function reorderGallery(Gallery $gallery, int $newOrder)
    {
        $this->authorize('update', $gallery);

        $gallery->reorder($newOrder);
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
            ->orderBy('created_at', 'desc')
            ->paginate(5);
    }
}; ?>

<x-app-layout>
    @volt('pages.portfolio')
        <div class="max-w-lg mx-auto">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-4 w-full">
                <div class="max-sm:w-full sm:flex-1">
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" level="1">{{ __('Portfolio') }}</flux:heading>
                    @if ($this->team->portfolio_public_disabled)
                        <flux:badge size="sm">{{ __('Public portfolio is disabled') }}</flux:badge>
                    @endif
                </div>
                <flux:subheading size="lg">
                    {{ __('Manage your public portfolio galleries.') }}
                </flux:subheading>
                </div>
                <div>
                    @if ($this->team->portfolio_public_disabled)
                        <flux:button
                            icon="lock-open"
                            :tooltip="__('Enable Public Portfolio')"
                            wire:click="enablePortfolioPage"
                        >
                            {{ __('Enable') }}
                        </flux:button>
                    @else
                        <flux:button
                            icon="lock-closed"
                            :tooltip="__('Disable Public Portfolio')"
                            wire:click="disablePortfolioPage"
                        >
                            {{ __('Disable') }}
                        </flux:button>
                    @endif
                </div>
                <flux:separator variant="subtle" class="mt-6" />
            </div>

            <div class="space-y-4">
                @if ($this->portfolioGalleries?->isNotEmpty())
                    <ul x-data="{
                        handleReorder: (item, position) => {
                            $wire.call('reorderGallery', item, position);
                        }
                    }" x-sort="handleReorder">
                        @foreach ($this->portfolioGalleries as $gallery)
                            <li x-sort:item="{{ $gallery->id }}">
                                @if (!$loop->first)
                                    <flux:separator variant="subtle" />
                                @endif
                                <div class="flex items-center justify-between py-6">
                                    <div class="flex items-center gap-4">
                                        <flux:button x-sort:handle variant="subtle" size="sm" inset="top bottom" class="cursor-move touch-manipulation" square>
                                            <flux:icon.bars-2 variant="mini" />
                                        </flux:button>
                                        <img
                                            src="{{ $gallery->coverPhoto?->small_thumbnail_url ?? $gallery->photos()->first()?->small_thumbnail_url }}"
                                            alt=""
                                            class="size-16 rounded-lg object-cover"
                                        />
                                        <div>
                                            <flux:heading>{{ $gallery->name }}</flux:heading>
                                             <flux:text>{{ $gallery->photos()->count() }} {{ __('photos') }}</flux:text>
                                        </div>
                                    </div>
                                <flux:button wire:click="removeFromPortfolio({{ $gallery->id }})" variant="subtle" size="sm" square>
                                    <flux:icon.x-mark variant="mini" />
                                </flux:button>
                                </div>
                            </li>
                        @endforeach
                     </ul>

                     <div>
                         <flux:modal.trigger name="available-galleries">
                             <flux:button icon="plus" variant="filled">{{ __('Add Galleries') }}</flux:button>
                         </flux:modal.trigger>
                     </div>
                 @else
                     <flux:callout icon="briefcase" variant="secondary">
                         <flux:callout.heading>{{ __('Add Galleries to Portfolio') }}</flux:callout.heading>
                         <flux:callout.text>
                             {{ __('Add galleries to your portfolio to showcase your work to visitors.') }}
                         </flux:callout.text>
                         <x-slot name="actions">
                             <flux:modal.trigger name="available-galleries">
                                 <flux:button>{{ __('Add Galleries') }}</flux:button>
                             </flux:modal.trigger>
                         </x-slot>
                     </flux:callout>
                 @endif
             </div>

             <flux:modal name="available-galleries" class="md:w-[32rem]">
                 <div class="space-y-6">
                     <div>
                         <flux:heading size="lg">{{ __('Add Galleries to Portfolio') }}</flux:heading>
                         <flux:text class="mt-2">{{ __('Select galleries to add to your public portfolio.') }}</flux:text>
                     </div>

                     @if ($this->availableGalleries?->isNotEmpty())
                         <ul>
                             @foreach ($this->availableGalleries as $gallery)
                                 <li>
                                     @if (!$loop->first)
                                         <flux:separator variant="subtle" />
                                     @endif
                                     <div class="flex items-center justify-between py-6">
                                         <div class="flex items-center gap-4">
                                              <img
                                                  src="{{ $gallery->coverPhoto?->small_thumbnail_url ?? $gallery->photos()->first()?->small_thumbnail_url }}"
                                                  alt=""
                                                  class="size-16 rounded-lg object-cover"
                                              />
                                             <div>
                                                 <flux:heading size="sm">{{ $gallery->name }}</flux:heading>
                                                 <flux:text>{{ $gallery->photos()->count() }} {{ __('photos') }}</flux:text>
                                             </div>
                                         </div>
                                         <flux:button wire:click="addToPortfolio({{ $gallery->id }})" variant="subtle" size="sm" square>
                                             <flux:icon.plus variant="mini" />
                                         </flux:button>
                                     </div>
                                 </li>
                             @endforeach
                         </ul>
                     @else
                         <flux:callout variant="secondary">
                             <flux:callout.heading>{{ __('No Available Galleries') }}</flux:callout.heading>
                             <flux:callout.text>{{ __('All your galleries are already in your portfolio.') }}</flux:callout.text>
                         </flux:callout>
                     @endif

                     @if ($this->availableGalleries->hasPages())
                         <div class="pt-4">
                             <flux:pagination :paginator="$this->availableGalleries" />
                         </div>
                     @endif
                 </div>
             </flux:modal>
         </div>
     @endvolt
    @assets
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>
    @endassets
</x-app-layout>
