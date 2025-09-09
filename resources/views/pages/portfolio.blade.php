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
                        <ul class="mt-6" x-data="{
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

                 <!-- Add Galleries Button -->
                 <div class="mt-6">
                     <flux:modal.trigger name="available-galleries">
                         <flux:button icon="plus" variant="filled">{{ __('Add Galleries to Portfolio') }}</flux:button>
                     </flux:modal.trigger>
                 </div>

                 <flux:modal name="available-galleries" class="md:w-[32rem]">
                     <div class="space-y-6">
                         <div>
                             <flux:heading size="lg">{{ __('Add Galleries to Portfolio') }}</flux:heading>
                             <flux:text class="mt-2">{{ __('Select galleries to add to your public portfolio.') }}</flux:text>
                         </div>

                         @if ($this->availableGalleries?->isNotEmpty())
                             <ul class="space-y-4">
                                 @foreach ($this->availableGalleries as $gallery)
                                     <li>
                                         @if (!$loop->first)
                                             <flux:separator variant="subtle" />
                                         @endif
                                         <div class="flex items-center justify-between py-4">
                                             <div class="flex items-center gap-4">
                                                 <img
                                                     src="{{ $gallery->photos()->first()?->thumbnail_url }}"
                                                     alt=""
                                                     class="size-16 rounded-lg object-cover"
                                                 />
                                                 <div>
                                                     <flux:heading size="sm">{{ $gallery->name }}</flux:heading>
                                                     <flux:text>{{ $gallery->photos()->count() }} photos</flux:text>
                                                 </div>
                                             </div>
                                             <flux:button wire:click="addToPortfolio({{ $gallery->id }})" variant="subtle" size="xs" square>
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
        </div>
    @endvolt
    @assets
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>
    @endassets
</x-app-layout>
