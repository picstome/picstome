<?php

use App\Traits\InteractsWithTallStackModals;
use App\Livewire\Forms\PhotoshootForm;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTallStackModals;
    use WithPagination;

    public PhotoshootForm $form;

    public function save()
    {
        $this->authorize('create', Photoshoot::class);

        $this->form->store();

        $this->closeModal('create-photoshoot');
    }

    #[Computed]
    public function photoshoots()
    {
        return Auth::user()->currentTeam
            ->photoshoots()
            ->with([
                'customer',
                'latestGalleryWithImages.firstImagePhoto.gallery',
            ])
            ->withCount(['galleries', 'photos'])
            ->latest('date')
            ->paginate(24);
    }

    #[Computed]
    public function customers()
    {
        return Auth::user()->currentTeam
            ->customers()
            ->orderBy('name')
            ->get();
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ __('Photoshoots') }}</x-ts-heading>
            <x-ts-subheading>{{ __('View, create, and manage your photoshoots.') }}</x-ts-subheading>
        </div>
        <div>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-photoshoot' : 'login'">
                <x-ts-button variant="primary">{{ __('Create photoshoot') }}</x-ts-button>
            </x-ts-modal-trigger>
        </div>
    </div>

    @if ($this->photoshoots?->isNotEmpty())
        <div class="mt-12">
            <div id="photoshoots-grid" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($this->photoshoots as $photoshoot)
                    @php($cover = $photoshoot->latestGalleryWithImages?->firstImagePhoto)

                    <x-ts-card
                        class="group relative overflow-hidden p-0! transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700"
                    >
                        <a
                            href="{{ route('photoshoots.show', ['photoshoot' => $photoshoot]) }}"
                            class="block"
                            wire:navigate
                        >
                            <div
                                class="relative aspect-3/2 w-full overflow-hidden rounded-t-lg bg-zinc-200 dark:bg-zinc-700"
                            >
                                @if ($cover)
                                    <img
                                        src="{{ $cover->small_thumbnail_url }}"
                                        alt="{{ $photoshoot->name }}"
                                        class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                        width="400"
                                        height="267"
                                    />
                                @else
                                    <div
                                        class="flex size-full items-center justify-center bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-900"
                                        data-test="photoshoot-cover-placeholder"
                                    >
                                        <x-icon name="camera" class="size-12 text-zinc-400 dark:text-zinc-500" />
                                        <span class="sr-only">{{ __('No photos') }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <x-ts-heading size="lg">{{ $photoshoot->name }}</x-ts-heading>

                                    @if ($photoshoot->photos_count > 0)
                                        <span
                                            class="inline-flex shrink-0 items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400"
                                            title="{{ __('photos') }}"
                                        >
                                            <x-icon name="photo" variant="micro" class="size-4" />
                                            {{ $photoshoot->photos_count }}
                                        </span>
                                    @endif
                                </div>

                                @if ($photoshoot->customer || $photoshoot->customer_name)
                                    <x-ts-text variant="subtle" size="sm">
                                        {{ $photoshoot->customer?->name ?? $photoshoot->customer_name }}
                                    </x-ts-text>
                                @endif

                                <div
                                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    @if ($photoshoot->formatted_date)
                                        <span class="inline-flex items-center gap-1">
                                            <x-icon name="calendar" variant="micro" class="size-4" />
                                            {{ $photoshoot->formatted_date }}
                                        </span>
                                    @endif

                                    @if ($photoshoot->location)
                                        <span class="inline-flex items-center gap-1">
                                            <x-icon name="map-pin" variant="micro" class="size-4" />
                                            {{ $photoshoot->location }}
                                        </span>
                                    @endif

                                    @if ($photoshoot->galleries_count > 0)
                                        <span class="inline-flex items-center gap-1" title="{{ __('Galleries') }}">
                                            <x-icon name="rectangle-stack" variant="micro" class="size-4" />
                                            {{ $photoshoot->galleries_count }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </x-ts-card>
                @endforeach
            </div>

            <div
                x-data
                x-on:click="
                    let el = $event.target
                    while (el && el !== $el) {
                        if (el.hasAttribute('wire:click')) {
                            document
                                .getElementById('photoshoots-grid')
                                ?.scrollIntoView({ behavior: 'smooth' })
                            break
                        }

                        el = el.parentElement
                    }
                "
                class="mt-6"
            >
                <x-ts-pagination :paginator="$this->photoshoots" />
            </div>
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="camera" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No photoshoots') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any photoshoots. Create one to get started.') }}
            </x-ts-subheading>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-photoshoot' : 'login'">
                <x-ts-button variant="primary">
                    {{ __('Create photoshoot') }}
                </x-ts-button>
            </x-ts-modal-trigger>
        </div>
    @endif

    <x-ts-modal name="create-photoshoot" class="w-full sm:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Create a new photoshoot') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Please enter your photoshoot details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.name" :label="__('Photoshoot Name')" type="text" />

            @if ($this->customers)
                <x-ts-select wire:model.live="form.customer" :label="__('Customer')" variant="listbox" searchable>
                    <x-ts-select-option value="">{{ __('New customer') }}</x-ts-select-option>
                    @foreach ($this->customers as $customer)
                        <x-ts-select-option value="{{ $customer->id }}">
                            {{ $customer->name }}{{ $customer->email ? " ({$customer->email})" : '' }}
                        </x-ts-select-option>
                    @endforeach
                </x-ts-select>
            @endif

            @if (empty($this->form->customer))
                <x-ts-input wire:model="form.customerName" :label="__('Customer Name')" type="text" />
                <x-ts-input wire:model="form.customerEmail" :label="__('Customer Email')" type="email" />
            @endif

            <div class="grid grid-cols-2 gap-4">
                <x-ts-input wire:model="form.date" :label="__('Date')" type="date" />
                <x-ts-input wire:model="form.location" :label="__('Location')" type="text" />
            </div>
            <x-ts-input wire:model="form.price" :label="__('Price')" type="text" />
            <x-ts-textarea wire:model="form.comment" :label="__('Comment')" rows="3" />

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>
</div>
