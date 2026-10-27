<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $search = null;

    public function viewGallery($galleryId)
    {
        return $this->redirect("/galleries/{$galleryId}", navigate: true);
    }

    public function viewPhotoshoot($photoshootId)
    {
        return $this->redirect("/photoshoots/{$photoshootId}", navigate: true);
    }

    public function viewContract($contractId)
    {
        return $this->redirect("/contracts/{$contractId}", navigate: true);
    }

    public function viewPhoto($galleryId, $photoId)
    {
        return $this->redirect("/galleries/{$galleryId}/photos/{$photoId}", navigate: true);
    }

    #[Computed]
    public function galleries()
    {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->galleries()
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function contracts()
    {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->contracts()
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function photos()
    {
        if (! $this->search) {
            return collect();
        }

        return $this->team->galleries()
            ->with(['photos' => fn ($query) => $query->where('name', 'like', '%'.$this->search.'%')->limit(20)])
            ->get()
            ->pluck('photos')
            ->flatten()
            ->take(20);
    }

    #[Computed]
    public function photoshoots()
    {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->photoshoots()
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function customers()
    {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->customers()
            ->where(function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('phone', 'like', '%'.$this->search.'%');
            })
            ->limit(20)
            ->get();
    }

    public function viewCustomer($customerId)
    {
        return $this->redirect("/customers/{$customerId}", navigate: true);
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<div class="flex w-full max-h-[76vh] flex-col overflow-hidden rounded-xl bg-white shadow-lg dark:bg-dark-800">
    <div class="border-b border-zinc-100 p-3 dark:border-white/10">
        <x-input
            wire:model.live="search"
            placeholder="{{ __('Search...') }}"
            icon="magnifying-glass"
            autofocus
            clearable
            class="border-none shadow-none"
        />
    </div>

    @unless (is_null($this->search))
        <div class="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2">
            @unless ($this->customers->isEmpty())
                <x-ts-text class="px-2 py-1.5 text-xs font-medium tracking-wide uppercase">
                    {{ __('Customers') }}
                </x-ts-text>
                @foreach ($this->customers as $customer)
                    <button
                        type="button"
                        wire:click="viewCustomer('{{ $customer->id }}')"
                        wire:key="customer-{{ $customer->id }}"
                        class="hover:bg-brand-sage-soft dark:hover:bg-white/10 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
                    >
                        <span>{{ $customer->name }}</span>
                        @if ($customer->email)
                            <x-ts-text inline class="text-xs">{{ $customer->email }}</x-ts-text>
                        @endif
                    </button>
                @endforeach
            @endunless

            @unless ($this->galleries->isEmpty())
                <x-ts-text class="px-2 py-1.5 text-xs font-medium tracking-wide uppercase">
                    {{ __('Galleries') }}
                </x-ts-text>
                @foreach ($this->galleries as $gallery)
                    <button
                        type="button"
                        wire:click="viewGallery('{{ $gallery->id }}')"
                        wire:key="gallery-{{ $gallery->id }}"
                        class="hover:bg-brand-sage-soft dark:hover:bg-white/10 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
                    >
                        {{ $gallery->name }}
                    </button>
                @endforeach
            @endunless

            @unless ($this->photoshoots->isEmpty())
                <x-ts-text class="px-2 py-1.5 text-xs font-medium tracking-wide uppercase">
                    {{ __('Photoshoots') }}
                </x-ts-text>
                @foreach ($this->photoshoots as $photoshoot)
                    <button
                        type="button"
                        wire:click="viewPhotoshoot('{{ $photoshoot->id }}')"
                        wire:key="photoshoot-{{ $photoshoot->id }}"
                        class="hover:bg-brand-sage-soft dark:hover:bg-white/10 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
                    >
                        {{ $photoshoot->name }}
                    </button>
                @endforeach
            @endunless

            @unless ($this->contracts->isEmpty())
                <x-ts-text class="px-2 py-1.5 text-xs font-medium tracking-wide uppercase">
                    {{ __('Contracts') }}
                </x-ts-text>
                @foreach ($this->contracts as $contract)
                    <button
                        type="button"
                        wire:click="viewContract('{{ $contract->id }}')"
                        wire:key="contract-{{ $contract->id }}"
                        class="hover:bg-brand-sage-soft dark:hover:bg-white/10 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
                    >
                        {{ $contract->title }}
                    </button>
                @endforeach
            @endunless

            @unless ($this->photos->isEmpty())
                <x-ts-text class="px-2 py-1.5 text-xs font-medium tracking-wide uppercase">
                    {{ __('Photos') }}
                </x-ts-text>
                @foreach ($this->photos as $photo)
                    <button
                        type="button"
                        wire:click="viewPhoto('{{ $photo->gallery->id }}', '{{ $photo->id }}')"
                        wire:key="photo-{{ $photo->id }}"
                        class="hover:bg-brand-sage-soft dark:hover:bg-white/10 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
                    >
                        {{ $photo->name }}
                    </button>
                @endforeach
            @endunless
        </div>
    @endunless
</div>
