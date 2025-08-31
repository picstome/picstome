<?php

use App\Models\Photo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $search = null;

    #[Computed]
    public function galleries() {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->galleries()
            ->when($this->search, fn($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function contracts() {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->contracts()
            ->when($this->search, fn($query) => $query->where('title', 'like', '%' . $this->search . '%'))
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function photos() {
        if (! $this->search) {
            return collect();
        }

        return $this->team->galleries()
            ->with(['photos' => fn($query) => $query->where('name', 'like', '%' . $this->search . '%')->limit(20)])
            ->get()
            ->pluck('photos')
            ->flatten()
            ->take(20);
    }

    #[Computed]
    public function photoshoots() {
        if (! $this->search) {
            return collect();
        }

        return $this->team
            ->photoshoots()
            ->when($this->search, fn($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<flux:command class="border-none shadow-lg inline-flex flex-col max-h-[76vh]">
    <flux:command.input wire:model.live="search" placeholder="Search..." autofocus closable />

    @unless(is_null($this->search))
        <flux:command.items>
            @unless($this->galleries->isEmpty())
                <flux:text class="font-medium px-2 py-1.5 text-xs uppercase tracking-wide">
                    {{ __('Galleries') }}
                </flux:text>
                @foreach ($this->galleries as $gallery)
                    <flux:command.item href="/galleries/{{ $gallery->ulid }}">{{ $gallery->name }}</flux:command.item>
                @endforeach
            @endunless

            @unless($this->photoshoots->isEmpty())
                <flux:text class="font-medium px-2 py-1.5 text-xs uppercase tracking-wide">
                    {{ __('Photoshoots') }}
                </flux:text>
                @foreach ($this->photoshoots as $photoshoot)
                    <flux:command.item href="/photoshoots/{{ $photoshoot->ulid }}">{{ $photoshoot->name }}</flux:command.item>
                @endforeach
            @endunless

            @unless($this->contracts->isEmpty())
                <flux:text class="font-medium px-2 py-1.5 text-xs uppercase tracking-wide">
                    {{ __('Contracts') }}
                </flux:text>
                @foreach ($this->contracts as $contract)
                    <flux:command.item href="/contracts/{{ $contract->ulid }}">{{ $contract->title }}</flux:command.item>
                @endforeach
            @endunless

            @unless($this->photos->isEmpty())
                <flux:text class="font-medium px-2 py-1.5 text-xs uppercase tracking-wide">
                    {{ __('Photos') }}
                </flux:text>
                @foreach ($this->photos as $photo)
                    <flux:command.item href="/galleries/{{ $photo->gallery->ulid }}/photos/{{ $photo->id }}">{{ $photo->name }}</flux:command.item>
                @endforeach
            @endunless
        </flux:command.items>
    @endunless
</flux:command>
