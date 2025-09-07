<?php

use App\Events\PhotoAdded;
use App\Livewire\Forms\GalleryForm;
use App\Livewire\Forms\ShareGalleryForm;
use App\Models\Gallery;
use App\Models\Photo;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('galleries.show');

middleware(['auth', 'verified', 'can:view,gallery']);

new class extends Component
{
    use WithFileUploads;

    public Gallery $gallery;

    public GalleryForm $form;

    public ShareGalleryForm $shareForm;

    public Collection $favorites;

    public Collection $allPhotos;

    public array $existingPhotoNames = [];

    public ?Collection $photoshoots;

    #[Url]
    public $activeTab = 'all';

    #[Validate('required')]
    #[Validate(['photos.*' => 'image|max:51200'])]
    public $photos = [];

    public function mount(Gallery $gallery)
    {
        $this->form->setGallery($gallery);
        $this->shareForm->setGallery($gallery);
        $this->getFavorites();
        $this->getAllPhotos();
        $this->existingPhotoNames = $gallery->photos()->pluck('name')->toArray();
        $this->photoshoots = Auth::user()?->currentTeam?->photoshoots()->latest()->get();
    }

    public function save($index)
    {
        if(! isset($this->photos[$index])) {
            return;
        }

        $this->validate([
            "photos.{$index}" => [
                'image',
                'max:51200', // max. 50MB
                function ($attribute, $value, $fail) use ($index) {
                    $uploadedPhoto = $this->photos[$index];

                    if ($this->gallery->photos()->where('name', $uploadedPhoto->getClientOriginalName())->exists()) {
                        $fail(__('A photo with this name already exists.'));
                    }

                    if (! $this->hasSufficientStorage($uploadedPhoto)) {
                        $fail(__('You do not have enough storage space to upload this photo.'));
                    }
                },
            ],
        ]);

        $uploadedPhoto = $this->photos[$index];

        $this->addPhotoToGallery($uploadedPhoto);

        $this->getAllPhotos();
        $this->existingPhotoNames = $this->gallery->photos()->pluck('name')->toArray();
    }

    protected function hasSufficientStorage(UploadedFile $uploadedPhoto): bool
    {
        $photoSize = $uploadedPhoto->getSize();

        return $this->gallery->team->canStoreFile($photoSize);
    }

    protected function addPhotoToGallery(UploadedFile $uploadedPhoto): void
    {
        $photo = $this->gallery->addPhoto($uploadedPhoto);

        PhotoAdded::dispatch($photo);
    }

    public function share()
    {
        tap($this->shareForm->gallery->is_shared, function ($previouslyShared) {
            $this->shareForm->gallery->is_shared = true;

            $this->shareForm->update();

            Flux::modal('share')->close();

            if (! $previouslyShared) {
                Flux::modal('share-link')->show();
            }
        });

        $this->gallery = $this->gallery->fresh();
        $this->getAllPhotos();
    }

    public function disableSharing()
    {
        $this->gallery->update(['is_shared' => false]);
    }

    public function deletePhoto(Photo $photo)
    {
        $this->authorize('delete', $photo);

        $photo->deleteFromDisk()->delete();

        $this->getFavorites();
        $this->getAllPhotos();

        $this->existingPhotoNames = $this->gallery->photos()->pluck('name')->toArray();
    }

    public function update()
    {
        $this->form->update();

        $this->gallery = $this->gallery->fresh();
        $this->getAllPhotos();

        $this->modal('edit')->close();
    }

    public function delete()
    {
        $this->gallery->deletePhotos()->delete();

        $this->redirect(route('galleries'));
    }

    #[On('photo-favorited')]
    public function getFavorites()
    {
        $favorites = $this->gallery->photos()->favorited()->with('gallery')->get();

        $this->favorites = $favorites->naturalSortBy('name');
    }

    public function getAllPhotos()
    {
        $photos = $this->gallery->photos()->with('gallery')->get();

        $this->allPhotos = $photos->naturalSortBy('name');
    }

    public function with()
    {
        return [];
    }
}; ?>

<x-app-layout>
    @volt('pages.galleries.show')
        <div>
            <div class="max-lg:hidden">
                <flux:button :href="route('galleries')" icon="chevron-left" variant="subtle" inset>
                    {{ __('Galleries') }}
                </flux:button>
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $gallery->name }}</x-heading>
                        @if ($gallery->is_shared)
                            <flux:badge color="lime" size="sm">{{ __('Sharing') }}</flux:badge>
                        @endif
                    </div>
                    <x-subheading class="mt-2">
                        {{ __('View, upload, and manage your gallery photos.') }}
                        @if ($gallery->expiration_date)
                            &bull; {{ __('Expires on') }} {{ $gallery->expiration_date->isoFormat('l') }}
                        @endif
                    </x-subheading>
                    @if ($allPhotos->isNotEmpty())
                        <div class="mt-2 text-sm text-zinc-500 dark:text-white/70">
                            {{ $allPhotos->count() }} {{ $allPhotos->count() === 1 ? __('photo') : __('photos') }} • {{ $gallery->getFormattedStorageSize() }} {{ __('total storage') }}
                        </div>
                    @endif
                </div>
                <div class="flex gap-4">
                    <flux:dropdown>
                        <flux:button icon="ellipsis-horizontal" variant="subtle" />

                        <flux:menu>
                            <flux:menu.item
                                :href="route('galleries.download', ['gallery' => $gallery])"
                                icon="cloud-arrow-down"
                            >
                                {{ __('Download') }}
                            </flux:menu.item>

                            @if($favorites->isNotEmpty())
                                <flux:modal.trigger name="favorite-list">
                                    <flux:menu.item icon="heart">{{ __('Favorite list') }}</flux:menu.item>
                                </flux:modal.trigger>
                            @endif

                            <flux:modal.trigger name="edit">
                                <flux:menu.item icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
                            </flux:modal.trigger>

                            <flux:menu.separator />

                            <flux:menu.item
                                wire:click="delete"
                                wire:confirm="{{ __('Are you sure?') }}"
                                variant="danger"
                                icon="trash"
                            >
                                {{ __('Delete') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    @if ($gallery->is_shared)
                        <flux:button.group>
                            <flux:button wire:click="disableSharing">{{ __('Stop sharing') }}</flux:button>
                            <flux:dropdown align="end">
                                <flux:button icon="chevron-down" />

                                <flux:menu>
                                    <flux:modal.trigger name="share-link">
                                        <flux:menu.item icon="link">{{ __('Get share link') }}</flux:menu.item>
                                    </flux:modal.trigger>
                                    <flux:modal.trigger name="share">
                                        <flux:menu.item icon="cog">{{ __('Share settings') }}</flux:menu.item>
                                    </flux:modal.trigger>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:button.group>
                    @else
                        <flux:modal.trigger name="share">
                            <flux:button>{{ __('Share') }}</flux:button>
                        </flux:modal.trigger>
                    @endif

                    <flux:modal.trigger name="add-photos">
                        <flux:button variant="primary">{{ __('Add photos') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($allPhotos->isNotEmpty())
                <div class="mt-8 max-sm:-mx-5">
                    <flux:navbar class="border-b border-zinc-800/10 dark:border-white/20">
                        <flux:navbar.item
                            @click="$wire.activeTab = 'all'"
                            x-bind:data-current="$wire.activeTab === 'all'"
                        >
                            {{ __('All photos') }}
                        </flux:navbar.item>
                        <flux:navbar.item
                            @click="$wire.activeTab = 'favorited'"
                            x-bind:data-current="$wire.activeTab === 'favorited'"
                        >
                            {{ __('Favorited') }}
                        </flux:navbar.item>
                        @if($favorites->isNotEmpty())
                            <flux:modal.trigger name="favorite-list">
                                <flux:badge size="sm" as="button">{{ __('As list') }}</flux:badge>
                            </flux:modal.trigger>
                        @endif
                    </flux:navbar>

                    <div x-show="$wire.activeTab === 'all'" class="pt-1">
                        <div
                            class="grid grid-flow-dense grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1"
                        >
                            @foreach ($allPhotos as $photo)
                                <livewire:photo-item :$photo :key="'photo-'.$photo->id" :html-id="'photo-'.$photo->id" />
                            @endforeach
                        </div>
                    </div>

                    <div x-show="$wire.activeTab === 'favorited'" class="pt-1">
                        <div
                            class="grid grid-flow-dense grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1"
                        >
                            @foreach ($favorites as $photo)
                                <livewire:photo-item :$photo :asFavorite="true" :key="'favorite-'.$photo->id" :html-id="'favorite-'.$photo->id" />
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn’t find any photos. Add one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger name="add-photos">
                        <flux:button variant="primary">
                            {{ __('Add photos') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="add-photos" class="w-full sm:max-w-lg">
                <form class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add photos') }}</flux:heading>
                        <flux:subheading>{{ __('Select photos for your gallery.') }}</flux:subheading>
                    </div>

                    @if (auth()->user()?->currentTeam->storage_used_percent > 95)
                        <flux:callout icon="bolt" variant="secondary">
                            <flux:callout.heading>{{ __('Low storage space') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('You have less than 5% storage remaining. ') }}
                            </flux:callout.text>
                            <x-slot name="actions">
                                <flux:button :href="route('subscribe')" variant="primary">{{ __('Upgrade') }}</flux:button>
                            </x-slot>
                        </flux:callout>
                    @endif

                    <div x-data="multiFileUploader">
                        <!-- File Input -->
                        <flux:input
                            @change="handleFileSelect($event)"
                            type="file"
                            accept=".jpg, .jpeg, .png, .tiff"
                            multiple
                            class="mb-4"
                        />

                        <flux:error name="photos" />

                        <flux:error name="photos.*" />

                        <div
                            x-show="
                                files.filter((file) => file.status === 'pending' || file.status === 'queued' || file.status === 'uploading')
                                    .length > 0
                            "
                            class="text-sm font-medium text-zinc-800 dark:text-white"
                        >
                            <span
                                x-text="
                                    files.filter((file) => file.status === 'pending' || file.status === 'queued' || file.status === 'uploading')
                                        .length
                                "
                            ></span>
                            {{ __('remaining files.') }}
                        </div>

                        <!-- Upload Queue Display -->
                        <div class="space-y-2">
                            <template x-for="(fileObj, index) in files" :key="index">
                                <div
                                    x-show="fileObj.status !== 'completed'"
                                    class="relative mt-3 flex flex-1 justify-between gap-3 rounded-lg border border-zinc-800/15 bg-white p-4 shadow-xs [--haze-border:color-mix(in_oklab,_var(--color-accent-content),_transparent_80%)] [--haze-light:color-mix(in_oklab,_var(--color-accent),_transparent_98%)] [--haze:color-mix(in_oklab,_var(--color-accent-content),_transparent_97.5%)] *:relative after:absolute after:-inset-px after:rounded-lg hover:border-[var(--haze-border)] hover:after:bg-[var(--haze-light)] dark:border-white/10 dark:bg-white/10 dark:[--haze:color-mix(in_oklab,_var(--color-accent-content),_transparent_90%)] dark:hover:border-white/10 dark:hover:bg-white/15 dark:hover:after:bg-white/[4%]"
                                >
                                    <div class="flex w-full items-center gap-4">
                                        <div
                                            x-text="fileObj.file.name"
                                            class="flex-1 text-sm font-medium text-zinc-800 dark:text-white"
                                        ></div>
                                        <div class="text-sm text-zinc-500 dark:text-white/70" x-show="fileObj.status !== 'duplicated'">
                                            <span x-text="fileObj.progress + '%'"></span>
                                        </div>
                                        <flux:badge
                                            x-show="!['failed', 'duplicated'].includes(fileObj.status)"
                                            x-text="fileObj.status"
                                            size="sm"
                                            color="zinc"
                                        ></flux:badge>
                                        <flux:badge
                                            x-show="fileObj.status === 'failed'"
                                            x-text="fileObj.status"
                                            size="sm"
                                            color="red"
                                        ></flux:badge>
                                        <flux:badge
                                            x-show="fileObj.status === 'duplicated'"
                                            x-text="fileObj.status"
                                            size="sm"
                                            color="yellow"
                                        ></flux:badge>

                                        <flux:button
                                            class="z-10"
                                            x-show="fileObj.status === 'failed'"
                                            x-on:click="retryUpload(index)"
                                            size="sm"
                                            icon="arrow-path"
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="share" class="w-full sm:max-w-lg">
                <form wire:submit="share" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Share gallery') }}</flux:heading>
                        <flux:subheading>{{ __('Customize your shared gallery.') }}</flux:subheading>
                    </div>

                    <flux:switch wire:model="shareForm.watermarked" :label="__('Watermark photos')" @click="$wire.shareForm.downloadable = false" />

                    <flux:switch wire:model="shareForm.downloadable" :label="__('Visitors can download photos')" x-bind:disabled="$wire.shareForm.watermarked" />

                    <flux:switch wire:model="shareForm.selectable" :label="__('Visitors can select photos')" />

                    <div x-show="$wire.shareForm.selectable">
                        <flux:switch wire:model="shareForm.limitedSelection" :label="__('Limit selection')" />
                    </div>

                    <div x-show="$wire.shareForm.limitedSelection">
                        <flux:input
                            wire:model="shareForm.selectionLimit"
                            :label="__('Limit photo selection')"
                            type="number"
                        />
                    </div>

                     <flux:switch wire:model="shareForm.passwordProtected" :label="__('Protect with a password')" />

                     <div x-show="$wire.shareForm.passwordProtected">
                         <flux:input wire:model="shareForm.password" type="password" :label="__('Password')" />
                     </div>

                     <flux:switch wire:model="shareForm.descriptionEnabled" :label="__('Add description')" />

                     <div x-show="$wire.shareForm.descriptionEnabled">
                         <flux:textarea wire:model="shareForm.description" :label="__('Description')" :placeholder="__('Add a description for your shared gallery...')" rows="3" />
                     </div>

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="share-link" class="w-full sm:max-w-lg">
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Gallery shared') }}</flux:heading>

                    <flux:input
                        icon="link"
                        :value="route('shares.show', ['gallery' => $gallery])"
                        :label="__('Share URL')"
                        readonly
                        copyable
                    />
                </div>
            </flux:modal>

            <flux:modal name="edit" class="w-full sm:max-w-lg">
                <form wire:submit="update" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit gallery') }}</flux:heading>
                        <flux:subheading>{{ __('Enter your gallery details.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="form.name" :label="__('Gallery name')" type="text" />

                    @if ($photoshoots)
                        <flux:select wire:model="form.photoshoot_id" :label="__('Photoshoot')" :placeholder="__('Choose photoshoot...')">
                            <flux:select.option value="">{{ __('No photoshoot') }}</flux:select.option>
                            <hr />
                            @foreach ($photoshoots as $photoshoot)
                                <flux:select.option value="{{ $photoshoot->id }}">
                                    {{ $photoshoot->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:input wire:model="form.expirationDate" :label="__('Expiration date')" :badge="__('Optional')" type="date" clearable />

                    <div class="flex">
                        <flux:spacer />

                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="favorite-list" class="w-full sm:max-w-lg">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Favorite list') }}</flux:heading>
                        <flux:subheading>{{ __('List of favorite photo names.') }}</flux:subheading>
                    </div>

                    <flux:input value="{{ implode(', ', $favorites->pluck('name')->toArray()) }}" readonly copyable />

                    @if ($favorites->isNotEmpty())
                        <ul class="ml-6 list-disc">
                            @foreach ($favorites as $favorite)
                                <li><flux:text>{{ $favorite->name }}</flux:text></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </flux:modal>
        </div>

        @script
            <script>
                document.addEventListener('livewire:navigated', () => {
                    const hash = window.location.hash;
                    if (hash) {
                        setTimeout(() => {
                            const element = document.querySelector(hash);
                            if (element) {
                                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }, 500);
                    }
                });

                Alpine.data('multiFileUploader', () => ({
                    files: [],
                    maxParallelUploads: 5,
                    activeUploads: 0,
                    maxUploadsPerMinute: 60,
                    uploadTimestamps: [], // Tracks when uploads started

                    init() {
                        // Clean up old timestamps every minute
                        setInterval(() => {
                            const oneMinuteAgo = Date.now() - 60000;
                            this.uploadTimestamps = this.uploadTimestamps.filter(
                                (timestamp) => timestamp > oneMinuteAgo,
                            );
                            this.processUploadQueue();
                        }, 1000); // Check every second
                    },

                    handleFileSelect(event) {
                        const selectedFiles = Array.from(event.target.files);
                        selectedFiles.forEach((file) => {
                            this.files.push({
                                file: file,
                                progress: 0,
                                status: 'pending',
                            });
                        });
                        this.processUploadQueue();
                    },

                    canUpload() {
                        // Check if we're below the rate limit
                        const oneMinuteAgo = Date.now() - 60000;
                        this.uploadTimestamps = this.uploadTimestamps.filter((timestamp) => timestamp > oneMinuteAgo);
                        return this.uploadTimestamps.length < this.maxUploadsPerMinute;
                    },

                    async processUploadQueue() {
                        // Get all files that are ready to upload (pending or queued)
                        const availableFiles = this.files.filter(
                            (f) => f.status === 'pending' || f.status === 'queued',
                        );

                        while (
                            this.activeUploads < this.maxParallelUploads &&
                            availableFiles.length > 0 &&
                            this.canUpload()
                        ) {
                            const fileObj = availableFiles.shift();
                            const fileIndex = this.files.indexOf(fileObj);
                            fileObj.status = 'pending'; // Ensure it's pending before upload
                            this.uploadFile(fileObj, fileIndex);
                        }

                        // Mark remaining files as queued if rate limit is reached
                        this.files.forEach((fileObj) => {
                            if (
                                (fileObj.status === 'pending' || fileObj.status === 'queued') &&
                                (!this.canUpload() || this.activeUploads >= this.maxParallelUploads)
                            ) {
                                fileObj.status = 'queued';
                            }
                        });
                    },

                    checkAllUploadsComplete() {
                        return (
                            this.files.every(
                                (file) => file.status === 'completed',
                                // file.status === 'completed' || file.status === 'failed'
                            ) && this.activeUploads === 0
                        );
                    },

                    uploadFile(fileObj, index) {
                        // Check for duplicate photo name
                        if ($wire.existingPhotoNames.includes(fileObj.file.name)) {
                            fileObj.status = 'duplicated';
                            this.activeUploads--;

                            setTimeout(() => {
                                this.processUploadQueue();
                            }, 10000); // Wait 10 seconds before continuing

                            return;
                        }

                        this.activeUploads++;
                        this.uploadTimestamps.push(Date.now());
                        fileObj.status = 'uploading';

                        const uploadName = `photos.${index}`;

                        $wire.upload(
                            uploadName,
                            fileObj.file,
                            () => {
                                // Success callback
                                $wire.save(index);
                                fileObj.status = 'completed';
                                fileObj.progress = 100;
                                this.activeUploads--;
                                this.processUploadQueue();

                                if (this.checkAllUploadsComplete()) {
                                    $flux.modals('add-photos').close();
                                }
                            },
                            (error) => {
                                // Error callback
                                fileObj.status = 'failed';
                                console.error('Upload failed:', error);
                                this.activeUploads--;
                                this.processUploadQueue();
                            },
                            (event) => {
                                // Progress callback
                                fileObj.progress = Math.round((event.loaded / event.total) * 100);
                            },
                        );
                    },

                    retryUpload(index) {
                        const fileObj = this.files[index];
                        fileObj.status = 'pending';
                        fileObj.progress = 0;
                        this.processUploadQueue();
                    },
                }));
            </script>
        @endscript
    @endvolt
</x-app-layout>
