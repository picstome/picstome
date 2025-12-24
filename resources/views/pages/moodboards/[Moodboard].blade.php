<?php

use App\Livewire\Forms\MoodboardForm;
use App\Models\Moodboard;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('moodboards.show');

middleware(['auth', 'verified', 'can:view,moodboard']);

new class extends Component
{
    public Moodboard $moodboard;

    public MoodboardForm $form;

    public Collection $photoshoots;

    public array $availablePhotos = [];

    public array $selectedPhotos = [];

    public array $photoIds = [];

    #[Url]
    public $activeTab = 'all';

    public function mount(Moodboard $moodboard)
    {
        $this->form->setMoodboard($moodboard);
        $this->photoshoots = Auth::user()?->currentTeam?->photoshoots()->latest()->get();
    }

    public function update()
    {
        $this->form->update();

        $this->moodboard = $this->moodboard->fresh();

        $this->modal('edit')->close();
    }

    public function delete()
    {
        $this->moodboard->delete();

        $this->redirect(route('moodboards'));
    }

    public function addSelectedPhotos()
    {
        foreach ($this->selectedPhotos as $photoId) {
            $photo = Photo::find($photoId);
            if ($photo && ! $this->moodboard->photos->contains($photoId)) {
                $this->moodboard->addPhoto($photo);
            }
        }

        $this->moodboard = $this->moodboard->fresh();
        $this->modal('add-photos')->close();
    }

    public function removePhoto(Photo $photo)
    {
        $this->moodboard->removePhoto($photo);

        $this->moodboard = $this->moodboard->fresh();
    }

    public function openAddPhotosModal()
    {
        $photos = Photo::query()
            ->whereHas('gallery', fn ($q) => $q->where('team_id', Auth::user()->currentTeam->id))
            ->get();

        $this->availablePhotos = $photos
            ->filter(fn ($photo) => ! $this->moodboard->photos->contains($photo->id))
            ->map(fn ($photo) => [
                'id' => $photo->id,
                'name' => $photo->name,
                'thumbnail_url' => $photo->small_thumbnail_url,
                'gallery_id' => $photo->gallery_id,
            ])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function moodboardPhotos()
    {
        return $this->moodboard->photos;
    }
}; ?>

<x-app-layout>
    @volt('pages.moodboards.show')
        <div>
            <div class="max-lg:hidden">
                <flux:button :href="route('moodboards')" icon="chevron-left" variant="subtle" inset>
                    {{ __('Moodboards') }}
                </flux:button>
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ $moodboard->name }}</x-heading>
                </div>
                <div class="flex gap-4">
                    <flux:dropdown>
                        <flux:button icon-trailing="chevron-down" variant="subtle">{{ __('Delete') }}</flux:button>

                        <flux:menu>
                            <flux:menu.item
                                wire:click="delete"
                                wire:confirm="{{ __('Are you sure?') }}"
                                variant="danger"
                            >
                                {{ __('Delete moodboard') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    <flux:modal.trigger name="edit">
                        <flux:button>{{ __('Edit') }}</flux:button>
                    </flux:modal.trigger>

                    <flux:button variant="primary" icon="plus">
                        <flux:modal.trigger name="add-photos">
                            {{ __('Add photos') }}
                        </flux:modal.trigger>
                    </flux:button>
                </div>
            </div>

            @if ($moodboard->description)
                <flux:subheading class="mt-6">{{ $moodboard->description }}</flux:subheading>
            @endif

            <div class="mt-8 flex gap-4">
                @if ($moodboard->is_public)
                    <flux:badge variant="success">{{ __('Public') }}</flux:badge>
                @else
                    <flux:badge variant="subtle">{{ __('Private') }}</flux:badge>
                @endif

                @if ($moodboard->photoshoot)
                    <flux:badge>
                        <a
                            :href="route('photoshoots.show', ['photoshoot' => $moodboard->photoshoot])"
                            class="text-inherit hover:underline"
                        >
                            {{ $moodboard->photoshoot->name }}
                        </a>
                    </flux:badge>
                @endif

                <flux:text variant="subtle">
                    {{ $moodboard->photos->count() }} {{ __('images') }} •
                    {{ $moodboard->created_at->format('M j, Y') }}
                </flux:text>
            </div>

            @if ($this->moodboardPhotos->isNotEmpty())
                <div class="mt-12">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        @foreach ($this->moodboardPhotos as $photo)
                            <div
                                class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/10"
                            >
                                @if ($photo->isImage())
                                    <img
                                        src="{{ $photo->small_thumbnail_url }}"
                                        alt="{{ $photo->name }}"
                                        class="aspect-3/2 w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        loading="lazy"
                                        width="400"
                                        height="267"
                                    />
                                @else
                                    <div
                                        class="flex aspect-3/2 w-full items-center justify-center bg-zinc-200 dark:bg-zinc-700"
                                    >
                                        <flux:icon.photo class="size-12 text-zinc-400 dark:text-zinc-500" />
                                    </div>
                                @endif

                                <button
                                    wire:click="removePhoto({{ $photo->id }})"
                                    wire:confirm="{{ __('Are you sure you want to remove this photo?') }}"
                                    class="absolute top-2 right-2 rounded-md bg-red-500 p-2 text-white opacity-0 transition-opacity group-hover:opacity-100 hover:bg-red-600"
                                >
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('Add photos from your library to get started.') }}
                    </flux:subheading>
                    <flux:button variant="primary">
                        <flux:modal.trigger name="add-photos">
                            {{ __('Add photos') }}
                        </flux:modal.trigger>
                    </flux:button>
                </div>
            @endif

            <flux:modal name="edit" class="w-full sm:max-w-lg">
                <form wire:submit="update" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit moodboard') }}</flux:heading>
                        <flux:subheading>{{ __('Update your moodboard details.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="form.name" :label="__('Moodboard name')" type="text" />

                    <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

                    @if ($this->photoshoots)
                        <flux:select wire:model="form.photoshoot_id" :label="__('Photoshoot')" variant="listbox">
                            <flux:select.option value="">{{ __('No photoshoot') }}</flux:select.option>
                            @foreach ($this->photoshoots as $photoshoot)
                                <flux:select.option value="{{ $photoshoot->id }}">
                                    {{ $photoshoot->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:switch wire:model="form.isPublic" :label="__('Make public')" />

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-photos" class="w-full sm:max-w-2xl">
                <div class="space-y-6" x-data="{ selected: [] }" x-on:modal-open.window="openAddPhotosModal()">
                    <div>
                        <flux:heading size="lg">{{ __('Add photos from library') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Select photos to add to your moodboard.') }}
                        </flux:subheading>
                    </div>

                    @if (count($availablePhotos) > 0)
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                            @foreach ($availablePhotos as $photo)
                                <label
                                    class="relative cursor-pointer overflow-hidden rounded-lg border-2 transition-all hover:border-zinc-400"
                                    :class="selected.includes({{ $photo['id'] }}) ? 'border-blue-500' : 'border-transparent'"
                                >
                                    <input
                                        type="checkbox"
                                        value="{{ $photo['id'] }}"
                                        wire:model.live="selectedPhotos"
                                        class="sr-only"
                                    />
                                    <img
                                        src="{{ $photo['thumbnail_url'] }}"
                                        alt="{{ $photo['name'] }}"
                                        class="aspect-3/2 w-full object-cover"
                                        loading="lazy"
                                        width="300"
                                        height="200"
                                    />
                                    <div
                                        class="absolute right-0 bottom-0 left-0 rounded-b-lg bg-black/60 px-2 py-1 text-xs text-white"
                                    >
                                        {{ $photo['name'] }}
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div class="flex">
                            <flux:spacer />

                            <flux:button
                                wire:click="addSelectedPhotos"
                                variant="primary"
                                :disabled="count($selectedPhotos) === 0"
                            >
                                {{ __('Add selected') }}
                            </flux:button>
                        </div>
                    @else
                        <flux:callout variant="secondary">
                            {{ __('No photos available to add.') }}
                        </flux:callout>
                    @endif
                </div>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
