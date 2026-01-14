<?php

use App\Events\PhotoAdded;
use App\Livewire\Forms\MoodboardForm;
use App\Models\Moodboard;
use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('moodboards.show');

middleware(['auth', 'verified', 'can:view,moodboard']);

new class extends Component
{
    use WithFileUploads;

    public Moodboard $moodboard;

    public MoodboardForm $form;

    public $photos = [];

    public function mount(Moodboard $moodboard)
    {
        $this->form->setMoodboard($moodboard);
    }

    public function save($index)
    {
        if (! isset($this->photos[$index])) {
            return;
        }

        $this->validate([
            "photos.{$index}" => [
                function ($attribute, $value, $fail) use ($index) {
                    $uploadedPhoto = $this->photos[$index];

                    if ($this->moodboard->photos()->where('name', $uploadedPhoto->getClientOriginalName())->exists()) {
                        $fail(__('A photo with this name already exists.'));
                    }

                    if (! $this->hasSufficientStorage($uploadedPhoto)) {
                        $fail(__('You do not have enough storage space to upload this photo.'));
                    }
                },
            ],
        ]);

        $uploadedPhoto = $this->photos[$index];

        $this->addPhotoToMoodboard($uploadedPhoto);
    }

    protected function hasSufficientStorage(UploadedFile $uploadedPhoto): bool
    {
        $photoSize = $uploadedPhoto->getSize();

        return $this->moodboard->team->canStoreFile($photoSize);
    }

    protected function addPhotoToMoodboard(UploadedFile $uploadedPhoto): void
    {
        $photo = $this->moodboard->addPhoto($uploadedPhoto);

        PhotoAdded::dispatch($photo);
    }

    public function deletePhoto(Photo $photo)
    {
        $this->authorize('delete', $photo);

        $photo->deleteFromDisk()->delete();
    }

    public function update()
    {
        $this->form->update();

        $this->moodboard = $this->moodboard->fresh();
    }

    public function delete()
    {
        $this->moodboard->deletePhotos()->delete();

        $this->redirect(route('moodboards'));
    }

    #[Computed]
    public function allPhotos()
    {
        $cacheKey = "moodboard:{$this->moodboard->id}:photos";

        return Cache::remember($cacheKey, now()->addHours(1), function () {
            return $this->moodboard->photos()
                ->get()
                ->naturalSortBy('name');
        });
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
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
                    <x-heading level="1" size="xl">{{ $moodboard->title }}</x-heading>
                    <x-subheading class="mt-2">
                        {{ __('View, upload, and manage your moodboard photos.') }}
                    </x-subheading>
                    @if ($moodboard->description)
                        <flux:text class="mt-2 text-zinc-600 dark:text-white/70">
                            {{ $moodboard->description }}
                        </flux:text>
                    @endif
                    @if ($this->allPhotos?->isNotEmpty())
                        <div class="mt-2 text-sm text-zinc-500 dark:text-white/70">
                            {{ $this->allPhotos->count() }}
                            {{ $this->allPhotos->count() === 1 ? __('photo') : __('photos') }} •
                            {{ $moodboard->getFormattedStorageSize() }} {{ __('total storage') }}
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:dropdown placement="bottom-end">
                        <flux:button.icon icon="ellipsis-horizontal" variant="subtle" />
                        <flux:dropdown.menu>
                            <flux:modal.trigger name="edit">
                                <flux:menu.item icon="pencil">{{ __('Edit moodboard') }}</flux:menu.item>
                            </flux:modal.trigger>
                            <flux:menu.item
                                icon="trash"
                                variant="danger"
                                x-on:click="$wire.delete()"
                            >
                                {{ __('Delete moodboard') }}
                            </flux:menu.item>
                        </flux:dropdown.menu>
                    </flux:dropdown>

                    <flux:modal.trigger name="add-photos">
                        <flux:button variant="primary">{{ __('Add photos') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($this->allPhotos?->isNotEmpty())
                <div class="mt-8 max-sm:-mx-5">
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($this->allPhotos as $photo)
                            <div class="group relative aspect-square">
                                <a
                                    href="#photo-{{ $photo->id }}"
                                    class="block"
                                >
                                    <img
                                        src="{{ $photo->thumbnail_url }}"
                                        alt="{{ $photo->name }}"
                                        class="aspect-square w-full object-cover"
                                        loading="lazy"
                                        id="photo-{{ $photo->id }}"
                                    />
                                </a>

                                <div class="absolute inset-0 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                    <div class="absolute right-2 top-2 flex gap-1">
                                        @if ($photo->isFavorited())
                                            <flux:button
                                                icon="heart-fill"
                                                size="xs"
                                                variant="solid"
                                                x-on:click.prevent="$wire.dispatchTo('photo-item', 'toggleFavorite', { photoId: {{ $photo->id }} })"
                                            />
                                        @else
                                            <flux:button
                                                icon="heart"
                                                size="xs"
                                                variant="solid"
                                                x-on:click.prevent="$wire.dispatchTo('photo-item', 'toggleFavorite', { photoId: {{ $photo->id }} })"
                                            />
                                        @endif

                                        <flux:dropdown placement="bottom-end">
                                            <flux:button.icon icon="ellipsis-vertical" size="xs" variant="solid" />
                                            <flux:dropdown.menu>
                                                <flux:menu.item
                                                    icon="trash"
                                                    variant="danger"
                                                    x-on:click="$wire.deletePhoto({{ $photo->id }})"
                                                >
                                                    {{ __('Delete photo') }}
                                                </flux:menu.item>
                                            </flux:dropdown.menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn't find any photos. Add one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger name="add-photos">
                        <flux:button variant="primary">
                            {{ __('Add photos') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="edit" class="w-full sm:max-w-lg">
                <form wire:submit="update" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit moodboard') }}</flux:heading>
                        <flux:subheading>{{ __('Update your moodboard details.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="form.title" :label="__('Title')" type="text" />

                    <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-photos" class="w-full sm:max-w-3xl">
                <div>
                    <flux:heading size="lg">{{ __('Add photos') }}</flux:heading>
                    <flux:subheading>{{ __('Upload photos to your moodboard.') }}</flux:subheading>
                </div>

                <div
                    class="mt-6"
                    x-data="{
                        files: [],
                        dragActive: false,
                        processFiles(event) {
                            const selectedFiles = Array.from(event.target.files || event.dataTransfer.files);
                            const validFiles = selectedFiles.filter(file => file.type.startsWith('image/') || file.type.startsWith('video/'));
                            if (validFiles.length > 0) {
                                $wire.photos = [...$wire.photos, ...validFiles];
                            }
                        },
                    }"
                    x-on:dragenter.prevent="dragActive = true"
                    x-on:dragleave.prevent="dragActive = false"
                    x-on:dragover.prevent
                    x-on:drop.prevent="dragActive = false; processFiles($event)"
                    x-on:click="$refs.fileInput.click()"
                    class="cursor-pointer rounded-lg border-2 border-dashed"
                    :class="dragActive ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/20' : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600'"
                >
                    <input
                        type="file"
                        x-ref="fileInput"
                        wire:model="photos"
                        multiple
                        accept="image/*,video/*"
                        class="hidden"
                        x-on:change="processFiles($event)"
                    />

                    <div class="flex flex-col items-center p-12 text-center">
                        <flux:icon.upload class="mb-4 size-10 text-zinc-400 dark:text-zinc-500" />
                        <flux:heading size="sm" level="3">{{ __('Drag and drop or click to upload') }}</flux:heading>
                        <flux:text variant="subtle" class="mt-2">
                            {{ __('Support for JPG, PNG, MP4, and WebM') }}
                        </flux:text>
                    </div>
                </div>

                @if ($this->photos)
                    <div class="mt-6 grid grid-cols-4 gap-4">
                        @foreach ($this->photos as $index => $photo)
                            <div class="relative aspect-square overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <img
                                    src="{{ $photo->temporaryUrl() }}"
                                    class="aspect-square w-full object-cover"
                                />
                                <flux:button
                                    icon="x"
                                    size="xs"
                                    variant="solid"
                                    class="absolute right-2 top-2"
                                    x-on:click="$wire.photos = $wire.photos.filter((_, i) => i !== {{ $index }})"
                                />
                                <flux:button
                                    icon="check"
                                    size="xs"
                                    variant="primary"
                                    class="absolute bottom-2 right-2"
                                    x-on:click="$wire.save({{ $index }})"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
