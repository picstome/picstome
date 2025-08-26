<?php

use App\Livewire\Forms\GalleryForm;
use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('galleries');

middleware(['auth', 'verified']);

new class extends Component
{
    use WithPagination;

    public GalleryForm $form;

    public function mount()
    {
        $this->form->expirationDate = now()->addYear()->format('Y-m-d');
    }

    public function save()
    {
        $this->authorize('create', Gallery::class);

        tap($this->form->store(), function ($gallery) {
            $this->redirect(route('galleries.show', ['gallery' => $gallery]));
        });
    }

    #[Computed]
    public function galleries()
    {
        return Auth::user()?->currentTeam
            ->galleries()
            ->latest()
            ->paginate(25);
    }
}; ?>

<x-app-layout>
    @volt('pages.galleries')
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Galleries') }}</x-heading>
                    <x-subheading>{{ __('View, create, and manage your contracts.') }}</x-subheading>
                </div>
                <flux:modal.trigger :name="auth()->check() ? 'create-gallery' : 'login'">
                    <flux:button variant="primary">{{ __('Create gallery') }}</flux:button>
                </flux:modal.trigger>
            </div>

            @if ($this->galleries?->isNotEmpty())
                <div class="mt-12">
                    <div
                        class="grid grid-flow-dense auto-rows-[263px] grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-x-4 gap-y-6"
                    >
                        @foreach ($this->galleries as $gallery)
                            <div
                                class="relative flex overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/10"
                            >
                                <a class="flex w-full" href="/galleries/{{ $gallery->id }}">
                                    <img
                                        src="{{ $gallery->photos()->first()?->thumbnail_url }}"
                                        alt=""
                                        class="mx-auto object-contain"
                                    />
                                </a>
                                <div
                                    class="absolute inset-x-0 bottom-0 flex gap-2 border-t border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900"
                                >
                                    <flux:heading>{{ $gallery->name }}</flux:heading>
                                    <flux:text>
                                        {{ $gallery->photos()->count() }} photos ·
                                        {{ $gallery->created_at->format('M j, Y') }}
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        <flux:pagination :paginator="$this->galleries" />
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No galleries') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any galleries. Create one to get started.') }}
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

                    <flux:input wire:model="form.expirationDate" :label="__('Expiration date')" :badge="__('Optional')" type="date" clearable />

                    <flux:switch wire:model="form.keepOriginalSize" :label="__('Keep photos at their original size')" />

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
