<?php

use App\Livewire\Forms\GalleryForm;
use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Layout('layouts.app')]
    public GalleryForm $form;

    public function mount()
    {
        $this->form->expirationDate = now()->addMonth()->format('Y-m-d');
    }

    public function save()
    {
        $this->authorize('create', Gallery::class);

        tap($this->form->store(), function ($gallery) {
            $this->redirect(route('galleries.show', ['gallery' => $gallery]));
        });
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function galleries()
    {
        return $this->team->galleries()
            ->with(['coverPhoto'])
            ->latest()
            ->paginate(24);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-heading level="1" size="xl">{{ __('Galleries') }}</x-heading>
            <x-subheading>{{ __('View, create, and manage your galleries.') }}</x-subheading>
        </div>
        <flux:modal.trigger :name="auth()->check() ? 'create-gallery' : 'login'">
            <flux:button variant="primary">{{ __('Create gallery') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->galleries?->isNotEmpty())
        <div class="mt-12">
            <div id="grid" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->galleries as $gallery)
                    <flux:card
                        class="group relative overflow-hidden p-0! transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700"
                    >
                        <a
                            href="{{ route('galleries.show', ['gallery' => $gallery]) }}"
                            class="block"
                            wire:navigate
                        >
                            @if ($gallery->coverPhoto && $gallery->coverPhoto->isImage())
                                <img
                                    src="{{ $gallery->coverPhoto->small_thumbnail_url }}"
                                    alt="{{ $gallery->name }}"
                                    class="aspect-3/2 w-full rounded-t-lg object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                    width="400"
                                    height="267"
                                />
                            @elseif ($gallery->firstImage())
                                <img
                                    src="{{ $gallery->firstImage()->small_thumbnail_url }}"
                                    alt="{{ $gallery->name }}"
                                    class="aspect-3/2 w-full rounded-t-lg object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                    width="400"
                                    height="267"
                                />
                            @else
                                <div
                                    class="flex aspect-3/2 w-full items-center justify-center rounded-t-lg bg-zinc-200 dark:bg-zinc-700"
                                >
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

                                    @if ($gallery->created_at)
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

            <div
                x-data
                x-on:click="
                    let el = $event.target
                    while (el && el !== $el) {
                        if (el.hasAttribute('wire:click')) {
                            document.getElementById('grid')?.scrollIntoView({ behavior: 'smooth' })
                            break
                        }
                        el = el.parentElement
                    }
                "
                class="mt-6"
            >
                <flux:pagination :paginator="$this->galleries" />
            </div>
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <flux:heading size="lg" level="2">{{ __('No galleries') }}</flux:heading>
            <flux:subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any galleries. Create one to get started.') }}
            </flux:subheading>
            <flux:modal.trigger :name="auth()->check() ? 'create-gallery' : 'login'">
                <flux:button variant="primary">
                    {{ __('Create gallery') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    @endif

    <flux:modal name="create-gallery" class="w-full sm:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create a new gallery') }}</flux:heading>
                <flux:subheading>{{ __('Enter your gallery details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Gallery name')" type="text" />

            <flux:input
                wire:model="form.expirationDate"
                :label="__('Expiration date')"
                :badge="$this->team?->subscribed() ? __('Optional') : null"
                type="date"
                :clearable="$this->team?->subscribed()"
            />

            @if (! $this->team?->subscribed())
                <flux:callout icon="bolt" variant="secondary">
                    <flux:callout.heading>{{ __('Subscribe for optional expiration') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Subscribe to make gallery expiration dates optional and clearable.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            <flux:switch wire:model="form.keepOriginalSize" :label="__('Keep photos at their original size')" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
