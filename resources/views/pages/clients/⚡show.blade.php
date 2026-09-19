<?php

use App\Models\Customer;
use App\Services\GalleryUnlockService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Customer $customer;

    public $password;

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name.' - '.__('Galleries'));
    }

    #[Computed]
    public function team()
    {
        return $this->customer->team;
    }

    #[Computed]
    public function galleries()
    {
        return $this->customer->galleries()
            ->where('is_shared', true)
            ->where(function ($query) {
                $query->whereNull('share_password')
                    ->orWhereIn('galleries.ulid', app(GalleryUnlockService::class)->unlockedUlids());
            })
            ->with('coverPhoto')
            ->latest('galleries.created_at')
            ->get();
    }

    #[Computed]
    public function hasLockedGalleries(): bool
    {
        return $this->customer->galleries()
            ->where('is_shared', true)
            ->whereNotNull('share_password')
            ->whereNotIn('galleries.ulid', app(GalleryUnlockService::class)->unlockedUlids())
            ->exists();
    }

    public function unlock()
    {
        if (! app(GalleryUnlockService::class)->unlockCustomerGalleries($this->customer, $this->password)) {
            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        $this->reset('password');
    }
}; ?>

<div class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="h-full">
        <div class="space-y-4 text-center">
            <a href="{{ route('handle.show', ['handle' => $this->team->handle]) }}" class="block space-y-4" wire:navigate>
                @if($this->team->brand_logo_icon_url)
                    <img src="{{ $this->team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $this->team->name }}" />
                @else
                    <flux:heading size="xl">{{ $this->team->name }}</flux:heading>
                @endif
            </a>
        </div>

        <flux:heading size="lg" class="mt-8 mb-6">{{ __('Your galleries') }}</flux:heading>

        @if ($this->hasLockedGalleries)
            <form wire:submit="unlock" class="mx-auto mb-8 w-full space-y-6 md:w-96">
                <div>
                    <flux:heading size="lg">{{ __('Protected galleries') }}</flux:heading>
                    <flux:subheading>{{ __('Enter a password to view protected galleries.') }}</flux:subheading>
                </div>
                <flux:input wire:model="password" :label="__('Password')" type="text" />
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Unlock') }}</flux:button>
                </div>
            </form>
        @endif

        @if ($this->galleries->isNotEmpty())
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->galleries as $gallery)
                    <flux:card class="group relative overflow-hidden hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors p-0!">
                        <a
                            wire:navigate
                            href="{{ route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]) }}"
                            class="block"
                        >
                            @if($gallery->coverPhoto)
                                <img
                                    src="{{ $gallery->coverPhoto->small_thumbnail_url }}"
                                    alt="{{ $gallery->name }}"
                                    class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105 rounded-t-lg"
                                />
                            @elseif($gallery->firstImage())
                                <img
                                    src="{{ $gallery->firstImage()->small_thumbnail_url }}"
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

                                <div class="flex items-center justify-between">
                                    <flux:text variant="subtle" size="sm">
                                        {{ $gallery->photosCount() }} {{ __('photos') }}
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
        @else
            <flux:text>{{ __('No galleries yet') }}</flux:text>
        @endif

        @include('partials.social-links', ['team' => $this->team])

        @unlesssubscribed($this->team)
            <div class="mt-10">
                @include('partials.powered-by', ['team' => $this->team])
            </div>
        @endsubscribed
    </div>
</div>
