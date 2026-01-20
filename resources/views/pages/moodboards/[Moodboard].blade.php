<?php

use App\Livewire\Forms\MoodboardForm;
use App\Models\Moodboard;
use App\Models\MoodboardPhoto;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
        $this->moodboard = $moodboard;
        $this->form->setMoodboard($moodboard);
    }

    public function update()
    {
        $this->authorize('update', $this->moodboard);

        $this->form->update();

        $this->moodboard = $this->moodboard->fresh();

        Flux::modal('edit')->close();
    }

    public function savePhoto($index)
    {
        if (! isset($this->photos[$index])) {
            return;
        }

        $this->validate([
            "photos.{$index}" => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,tiff,mp4,webm,ogg',
                'max:512000',
            ],
        ]);

        $uploadedPhoto = $this->photos[$index];

        if (! $this->hasSufficientStorage($uploadedPhoto)) {
            Flux::toast(__('You do not have enough storage space to upload this photo.'), variant: 'danger');

            return;
        }

        $this->addPhotoToMoodboard($uploadedPhoto);
    }

    protected function hasSufficientStorage(UploadedFile $uploadedPhoto): bool
    {
        $photoSize = $uploadedPhoto->getSize();

        return $this->moodboard->team->canStoreFile($photoSize);
    }

    protected function addPhotoToMoodboard(UploadedFile $uploadedPhoto): void
    {
        $this->moodboard->addPhoto($uploadedPhoto);

        Flux::toast(__('Photo added successfully.'));
    }

    public function deletePhoto(MoodboardPhoto $photo)
    {
        $this->authorize('delete', $photo);

        $photo->deleteFromDisk()->delete();
    }

    public function delete()
    {
        $this->authorize('delete', $this->moodboard);

        $this->moodboard->deletePhotos()->delete();

        return $this->redirect(route('moodboards'));
    }

    public function share()
    {
        $this->authorize('update', $this->moodboard);

        $this->moodboard->update(['is_shared' => true]);

        Flux::modal('share-link')->show();

        $this->moodboard = $this->moodboard->fresh();
    }

    public function disableSharing()
    {
        $this->authorize('update', $this->moodboard);

        $this->moodboard->update(['is_shared' => false]);
    }

    #[Computed]
    public function allPhotos()
    {
        return $this->moodboard->photos()->get()->naturalSortBy('name');
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
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $moodboard->title }}</x-heading>
                        @if ($moodboard->is_shared)
                            <flux:badge color="lime" size="sm">{{ __('Sharing') }}</flux:badge>
                        @endif
                    </div>
                    <x-subheading class="mt-2">
                        @if ($moodboard->description)
                            {{ $moodboard->description }}
                        @else
                            {{ __('Manage your moodboard media and collections.') }}
                        @endif
                    </x-subheading>
                    @if ($this->allPhotos?->isNotEmpty())
                        <div class="mt-2 text-sm text-zinc-500 dark:text-white/70">
                            {{ $this->allPhotos->count() }}
                            {{ $this->allPhotos->count() === 1 ? __('photo') : __('photos') }} •
                            {{ $moodboard->getFormattedStorageSize() }} {{ __('total storage') }}
                        </div>
                    @endif
                </div>
                <div class="flex gap-4">
                    <flux:dropdown>
                        <flux:button icon="ellipsis-horizontal" variant="subtle" />
                        <flux:menu>
                            <flux:modal.trigger name="edit">
                                <flux:menu.item icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
                            </flux:modal.trigger>
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

                    @if ($moodboard->is_shared)
                        <flux:button.group>
                            <flux:button wire:click="disableSharing">{{ __('Stop sharing') }}</flux:button>
                            <flux:dropdown align="end">
                                <flux:button icon="chevron-down" />

                                <flux:menu>
                                    <flux:modal.trigger name="share-link">
                                        <flux:menu.item icon="link">{{ __('Get share link') }}</flux:menu.item>
                                    </flux:modal.trigger>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:button.group>
                    @else
                        <flux:button wire:click="share">{{ __('Share') }}</flux:button>
                    @endif

                    <flux:modal.trigger name="add-photos">
                        <flux:button variant="primary">{{ __('Add media') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            @if ($this->allPhotos->isNotEmpty())
                <div class="mt-8 max-sm:-mx-5">
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($this->allPhotos as $photo)
                            <livewire:moodboard-photo-item
                                :$photo
                                :key="'photo-'.$photo->id"
                                :html-id="'photo-'.$photo->id"
                            />
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn\'t find any photos. Add one to get started.') }}
                    </flux:subheading>
                    <flux:modal.trigger name="add-photos">
                        <flux:button variant="primary">
                            {{ __('Add media') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif

            <flux:modal name="edit" class="w-full sm:max-w-lg">
                <form wire:submit="update" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit moodboard') }}</flux:heading>
                        <flux:subheading>{{ __('Enter your moodboard details.') }}</flux:subheading>
                    </div>
                    <flux:input wire:model="form.title" :label="__('Title')" type="text" />
                    <flux:textarea wire:model="form.description" :label="__('Description')" rows="4" />
                    <div class="flex">
                        <flux:spacer />
                        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-photos" class="w-full sm:max-w-lg">
                <form class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add media') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Upload images or videos to your moodboard.') }}
                        </flux:subheading>
                    </div>

                    @if (auth()->user()?->currentTeam->storage_used_percent > 95)
                        <flux:callout icon="bolt" variant="secondary">
                            <flux:callout.heading>{{ __('Low storage space') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('You have less than 5% storage remaining. ') }}
                            </flux:callout.text>
                            <x-slot name="actions">
                                <flux:button :href="route('subscribe')" variant="primary">
                                    {{ __('Upgrade') }}
                                </flux:button>
                            </x-slot>
                        </flux:callout>
                    @endif

                    <div
                        x-data="multiFileUploader"
                        x-on:dragover.prevent="dragActive = true"
                        x-on:dragleave.prevent="dragActive = false"
                        x-on:drop.prevent="handleDrop($event)"
                        :class="{'ring-2 ring-blue-400 ring-offset-4 rounded-sm': dragActive}"
                    >
                        <flux:input
                            @change="handleFileSelect($event)"
                            type="file"
                            accept=".jpg, .jpeg, .png, .tiff, .mp4, .webm, .ogg"
                            multiple
                        />
                        <flux:description class="mt-2 max-sm:hidden">
                            {{ __('Drag and drop files here, or click on choose files. Supported formats: JPG, JPEG, PNG, TIFF, MP4, WEBM, OGG.') }}
                        </flux:description>

                        <flux:error name="photos" />

                        <div
                            x-show="
                                files.filter(
                                    (file) =>
                                        file.status === 'pending' ||
                                        file.status === 'queued' ||
                                        file.status === 'uploading',
                                ).length > 0
                            "
                            class="mt-4 text-sm font-medium text-zinc-800 dark:text-white"
                        >
                            <span
                                x-text="
                                    files.filter(
                                        (file) =>
                                            file.status === 'pending' ||
                                            file.status === 'queued' ||
                                            file.status === 'uploading',
                                    ).length
                                "
                            ></span>
                            {{ __('remaining files.') }}
                        </div>

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
                                        <div
                                            class="text-sm text-zinc-500 dark:text-white/70"
                                            x-show="fileObj.status !== 'failed'"
                                        >
                                            <span x-text="fileObj.progress + '%'"></span>
                                        </div>
                                        <flux:badge
                                            x-show="! ['failed'].includes(fileObj.status)"
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

            <flux:modal name="share-link" class="w-full sm:max-w-lg">
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Moodboard shared') }}</flux:heading>

                    <flux:input
                        icon="link"
                        :value="route('shared-moodboards.show', ['moodboard' => $moodboard, 'slug' => $moodboard->slug])"
                        :label="__('Share URL')"
                        readonly
                        copyable
                    />
                </div>
            </flux:modal>
        </div>

        @script
            <script>
                Alpine.data('multiFileUploader', () => ({
                    files: [],
                    dragActive: false,
                    maxParallelUploads: 5,
                    activeUploads: 0,

                    init() {
                        setInterval(() => {
                            this.processUploadQueue();
                        }, 1000);
                    },

                    handleFileSelect(event) {
                        const selectedFiles = Array.from(event.target.files);
                        this.processFiles(selectedFiles);
                        this.processUploadQueue();
                    },

                    handleDrop(event) {
                        this.dragActive = false;
                        const dt = event.dataTransfer;
                        if (dt && dt.files && dt.files.length > 0) {
                            this.processFiles(Array.from(dt.files));
                            this.processUploadQueue();
                        }
                    },

                    async processUploadQueue() {
                        const availableFiles = this.files.filter(
                            (f) => f.status === 'pending' || f.status === 'queued',
                        );

                        while (
                            this.activeUploads < this.maxParallelUploads &&
                            availableFiles.length > 0
                        ) {
                            const fileObj = availableFiles.shift();
                            const fileIndex = this.files.indexOf(fileObj);
                            fileObj.status = 'pending';
                            this.uploadFile(fileObj, fileIndex);
                        }

                        this.files.forEach((fileObj) => {
                            if (
                                (fileObj.status === 'pending' || fileObj.status === 'queued') &&
                                (this.activeUploads >= this.maxParallelUploads)
                            ) {
                                fileObj.status = 'queued';
                            }
                        });
                    },

                    checkAllUploadsComplete() {
                        return (
                            this.files.every(
                                (file) => file.status === 'completed',
                            ) && this.activeUploads === 0
                        );
                    },

                    uploadFile(fileObj, index) {
                        this.activeUploads++;
                        fileObj.status = 'uploading';

                        const uploadName = `photos.${index}`;

                        $wire.upload(
                            uploadName,
                            fileObj.file,
                            () => {
                                $wire.savePhoto(index).then((result) => {
                                    if (result && result.errors && result.errors['photos.' + index]) {
                                        fileObj.status = 'failed';
                                        this.activeUploads--;
                                        setTimeout(() => {
                                            this.processUploadQueue();
                                        }, 10000);
                                    } else {
                                        fileObj.status = 'completed';
                                        fileObj.progress = 100;
                                        this.activeUploads--;
                                        this.processUploadQueue();

                                        if (this.checkAllUploadsComplete()) {
                                            $flux.modals('add-photos').close();
                                        }
                                    }
                                });
                            },
                            (error) => {
                                fileObj.status = 'failed';
                                console.error('Upload failed:', error);
                                this.activeUploads--;
                                this.processUploadQueue();
                            },
                            (event) => {
                                fileObj.progress = Math.round((event.loaded / event.total) * 100);
                            },
                        );
                    },

                    processFiles(files) {
                        files.forEach((file) => {
                            this.files.push({
                                file: file,
                                progress: 0,
                                status: 'pending',
                            });
                        });
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
