<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

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
            ->with(['coverPhoto', 'firstImagePhoto'])
            ->withCount('photos')
            ->get();
    }

    #[Computed]
    public function availableGalleries()
    {
        return $this->team?->galleries()
            ->private()
            ->with(['coverPhoto', 'firstImagePhoto'])
            ->withCount('photos')
            ->orderBy('created_at', 'desc')
            ->paginate(5);
    }
}; ?>

<div class="mx-auto max-w-lg">
    <div class="mb-4 flex w-full flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <div class="flex items-center gap-2">
                <x-ts-heading size="xl" level="1">{{ __('Portfolio') }}</x-ts-heading>
                @if ($this->team->portfolio_public_disabled)
                    <x-ts-badge size="sm">{{ __('Public portfolio is disabled') }}</x-ts-badge>
                @endif
            </div>
            <x-ts-subheading size="lg">
                {{ __('Manage your public portfolio galleries.') }}
            </x-ts-subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($this->team->portfolio_public_disabled)
                <x-ts-button
                    icon="lock-open"
                    :tooltip="__('Enable Public Portfolio')"
                    wire:click="enablePortfolioPage"
                    variant="primary"
                >
                    {{ __('Enable') }}
                </x-ts-button>
            @else
                <x-ts-button
                    :href="route('portfolio.index', ['handle' => $this->team->handle])"
                    target="_blank"
                    icon:trailing="arrow-top-right-on-square"
                    variant="primary"
                >
                    {{ __('View Portfolio') }}
                </x-ts-button>

                <x-ts-button
                    icon="lock-closed"
                    :tooltip="__('Disable Public Portfolio')"
                    wire:click="disablePortfolioPage"
                    variant="primary"
                    class="btn-ink"
                >
                    {{ __('Disable') }}
                </x-ts-button>
            @endif
        </div>
        <x-ts-separator variant="subtle" class="mt-6" />
    </div>

    <div class="space-y-4">
        @if ($this->portfolioGalleries?->isNotEmpty())
            <ul
                x-data="{
                    handleReorder: (item, position) => {
                        $wire.call('reorderGallery', item, position)
                    },
                }"
                x-sort="handleReorder"
            >
                @foreach ($this->portfolioGalleries as $gallery)
                    <li x-sort:item="{{ $gallery->id }}">
                        @if (! $loop->first)
                            <x-ts-separator variant="subtle" />
                        @endif

                        <div class="flex items-center justify-between py-6">
                            <div class="flex items-center gap-4">
                                <x-ts-button
                                    x-sort:handle
                                    variant="subtle"
                                    size="sm"
                                    inset="top bottom"
                                    class="cursor-move touch-manipulation"
                                    square
                                >
                                    <x-icon name="bars-2" variant="mini" />
                                </x-ts-button>
                                <img
                                    src="{{ $gallery->coverPhoto?->small_thumbnail_url ?? $gallery->firstImagePhoto?->small_thumbnail_url }}"
                                    alt=""
                                    class="size-16 rounded-lg object-cover"
                                />
                                <div>
                                    <x-ts-heading>{{ $gallery->name }}</x-ts-heading>
                                    <x-ts-text>{{ $gallery->photos_count }} {{ __('photos') }}</x-ts-text>
                                </div>
                            </div>
                            <x-ts-button
                                wire:click="removeFromPortfolio({{ $gallery->id }})"
                                variant="subtle"
                                size="sm"
                                square
                            >
                                <x-icon name="x-mark" variant="mini" />
                            </x-ts-button>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div>
                <x-ts-modal-trigger name="available-galleries">
                    <x-ts-button icon="plus" variant="primary" class="btn-ink">{{ __('Add Galleries') }}</x-ts-button>
                </x-ts-modal-trigger>
            </div>
        @else
            <x-ts-callout icon="briefcase" variant="secondary">
                <x-ts-callout-heading>{{ __('Add Galleries to Portfolio') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('Add galleries to your portfolio to showcase your work to visitors.') }}
                </x-ts-callout-text>
                <x-slot name="actions">
                    <x-ts-modal-trigger name="available-galleries">
                        <x-ts-button variant="primary" class="btn-ink">{{ __('Add Galleries') }}</x-ts-button>
                    </x-ts-modal-trigger>
                </x-slot>
            </x-ts-callout>
        @endif
    </div>

    <x-ts-modal name="available-galleries" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Add Galleries to Portfolio') }}</x-ts-heading>
                <x-ts-text class="mt-2">{{ __('Select galleries to add to your public portfolio.') }}</x-ts-text>
            </div>

            @if ($this->availableGalleries?->isNotEmpty())
                <ul>
                    @foreach ($this->availableGalleries as $gallery)
                        <li>
                            @if (! $loop->first)
                                <x-ts-separator variant="subtle" />
                            @endif

                            <div class="flex items-center justify-between py-6">
                                <div class="flex items-center gap-4">
                                    <img
                                        src="{{ $gallery->coverPhoto?->small_thumbnail_url ?? $gallery->firstImagePhoto?->small_thumbnail_url }}"
                                        alt=""
                                        class="size-16 rounded-lg object-cover"
                                    />
                                    <div>
                                        <x-ts-heading size="sm">{{ $gallery->name }}</x-ts-heading>
                                        <x-ts-text>{{ $gallery->photos_count }} {{ __('photos') }}</x-ts-text>
                                    </div>
                                </div>
                                <x-ts-button
                                    wire:click="addToPortfolio({{ $gallery->id }})"
                                    variant="subtle"
                                    size="sm"
                                    square
                                >
                                    <x-icon name="plus" variant="mini" />
                                </x-ts-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-ts-callout variant="secondary">
                    <x-ts-callout-heading>{{ __('No Available Galleries') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('All your galleries are already in your portfolio.') }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            @if ($this->availableGalleries->hasPages())
                <div class="pt-4">
                    <x-ts-pagination :paginator="$this->availableGalleries" />
                </div>
            @endif
        </div>
    </x-ts-modal>
</div>
@assets
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>
@endassets
