<?php

use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\GalleryForm;
use App\Models\FtpAccount;
use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use Interactions;
    use WithPagination;

    public GalleryForm $form;

    public function mount()
    {
        $this->form->expirationDate = now()->addMonth()->format('Y-m-d');
        $this->form->keepOriginalSize = (bool) (Auth::user()?->galleryDefaults()['keep_original_size'] ?? true);
    }

    public function save()
    {
        $this->authorize('create', Gallery::class);

        tap($this->form->store(), function ($gallery) {
            $this->redirect(route('galleries.show', ['gallery' => $gallery]));
        });
    }

    /**
     * Create today's shoot gallery, route live uploads to it, and jump
     * straight to its credentials.
     */
    public function startLiveShoot()
    {
        $this->authorize('create', Gallery::class);

        if (! $this->team->subscribed()) {
            return;
        }

        $name = FtpAccount::shootNameFor($this->team);

        if ($todayShoots = $this->team->galleries()->where('name', 'like', $name.'%')->count()) {
            $name = $name.' ('.($todayShoots + 1).')';
        }

        $gallery = $this->team->galleries()->create([
            'name' => $name,
            'is_public' => false,
            'keep_original_size' => (bool) ($this->team->owner->galleryDefaults()['keep_original_size'] ?? true),
        ]);

        if (! $account = $this->team->ftpAccount()->first()) {
            $account = FtpAccount::provisionForTeam($this->team);
        }

        if (! $account) {
            $this->toast()->error(__('Could not reach the upload server. Please try again.'))->send();

            $this->redirect(route('galleries.show', ['gallery' => $gallery]));

            return;
        }

        $account->routeTo($gallery);

        $this->redirect(route('galleries.show', ['gallery' => $gallery, 'live' => 1]));
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
            <x-ts-heading level="1" size="xl">{{ __('Galleries') }}</x-ts-heading>
            <x-ts-subheading>{{ __('View, create, and manage your galleries.') }}</x-ts-subheading>
        </div>
        <div class="flex gap-4">
            @if ($this->team?->subscribed())
                <x-ts-button icon="camera" wire:click="startLiveShoot" variant="primary" class="btn-ink">
                    {{ __('Connect camera') }}
                </x-ts-button>
            @endif

            <x-ts-modal-trigger :name="auth()->check() ? 'create-gallery' : 'login'">
                <x-ts-button variant="primary">{{ __('Create gallery') }}</x-ts-button>
            </x-ts-modal-trigger>
        </div>
    </div>

    @if ($this->galleries?->isNotEmpty())
        <div class="mt-12">
            <div id="grid" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->galleries as $gallery)
                    <x-ts-card
                        class="group relative overflow-hidden p-0! transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700"
                    >
                        <a href="{{ route('galleries.show', ['gallery' => $gallery]) }}" class="block" wire:navigate>
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
                                    <x-icon name="photo" class="size-12 text-zinc-400 dark:text-zinc-500" />
                                </div>
                            @endif

                            <div class="p-4">
                                <x-ts-heading size="lg" class="mb-2">
                                    {{ $gallery->name }}
                                </x-ts-heading>

                                <div class="flex items-center justify-between">
                                    <x-ts-text variant="subtle" size="sm">
                                        {{ $gallery->photosCount() }} {{ __('photos') }}
                                    </x-ts-text>

                                    @if ($gallery->created_at)
                                        <x-ts-text variant="subtle" size="sm">
                                            {{ $gallery->created_at->format('M j, Y') }}
                                        </x-ts-text>
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
                            document.getElementById('grid')?.scrollIntoView({ behavior: 'smooth' })
                            break
                        }
                        el = el.parentElement
                    }
                "
                class="mt-6"
            >
                <x-ts-pagination :paginator="$this->galleries" />
            </div>
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No galleries') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any galleries. Create one to get started.') }}
            </x-ts-subheading>
            <x-ts-modal-trigger :name="auth()->check() ? 'create-gallery' : 'login'">
                <x-ts-button variant="primary">
                    {{ __('Create gallery') }}
                </x-ts-button>
            </x-ts-modal-trigger>
        </div>
    @endif

    <x-ts-modal name="create-gallery" class="w-full sm:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Create a new gallery') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter your gallery details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.name" :label="__('Gallery name')" type="text" />

            <x-ts-input
                wire:model="form.expirationDate"
                :label="__('Expiration date')"
                :badge="$this->team?->subscribed() ? __('Optional') : null"
                type="date"
                :clearable="$this->team?->subscribed()"
            />

            @if (! $this->team?->subscribed())
                <x-ts-callout icon="bolt" variant="secondary">
                    <x-ts-callout-heading>{{ __('Subscribe for optional expiration') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('Subscribe to make gallery expiration dates optional and clearable.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            <x-ts-switch
                wire:model="form.keepOriginalSize"
                :label="__('Keep photos at their original size')"
                :description="__('This setting is shared with the create gallery modal.')"
            />

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>
</div>
