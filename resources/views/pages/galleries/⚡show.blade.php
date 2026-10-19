<?php

use App\Events\PhotoAdded;
use App\Livewire\Forms\GalleryForm;
use App\Livewire\Forms\ShareGalleryForm;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Gallery $gallery;

    public GalleryForm $form;

    public ShareGalleryForm $shareForm;

    public Collection $favorites;

    public Collection $commentedPhotos;

    public array $existingPhotoNames = [];

    public ?Collection $photoshoots;

    #[Url]
    public $activeTab = 'all';

    public $photos = [];

    public string $favoriteFileNames = '';

    public function mount(Gallery $gallery)
    {
        $this->authorize('view', $gallery);
        $this->form->setGallery($gallery);
        $this->shareForm->setGallery($gallery);
        $this->getFavorites();
        $this->getCommentedPhotos();
        $this->photoshoots = Auth::user()?->currentTeam?->photoshoots()->latest()->get();

        if (request()->boolean('live')) {
            Flux::modal('live-upload')->show();
        }
    }

    public function save($index, $action = 'version')
    {
        if (! isset($this->photos[$index])) {
            return;
        }

        $action = match ($action) {
            true => 'replace',
            false => 'version',
            'replace' => 'replace',
            'skip' => 'skip',
            default => 'version',
        };

        if ($action === 'skip') {
            return;
        }

        $uploadedPhoto = $this->photos[$index];

        $existingPhoto = $this->findPhotoByName($uploadedPhoto->getClientOriginalName());

        $this->validate([
            "photos.{$index}" => [
                function ($attribute, $value, $fail) use ($index, $existingPhoto, $action) {
                    $uploadedPhoto = $this->photos[$index];

                    if (! in_array(strtolower($uploadedPhoto->getClientOriginalExtension()), config('picstome.upload_extensions'), true)) {
                        $fail(__('This file type is not supported.'));
                    }

                    if (preg_match('#[/\\\\\x00-\x1F\x7F]#', $uploadedPhoto->getClientOriginalName())) {
                        $fail(__('The file name contains invalid characters.'));
                    }

                    if (strtolower($uploadedPhoto->getClientOriginalExtension()) === 'pdf' && ! $this->looksLikeAPdf($uploadedPhoto)) {
                        $fail(__('This file is not a valid PDF.'));
                    }

                    $replacedSize = $action === 'replace' && $existingPhoto ? $existingPhoto->size : 0;

                    if (! $this->hasSufficientStorage($uploadedPhoto, $replacedSize)) {
                        $fail(__('You do not have enough storage space to upload this photo.'));
                    }
                },
            ],
        ]);

        $uploadedPhoto = $this->photos[$index];
        $identityKey = Gallery::identityKey($uploadedPhoto->getClientOriginalName());

        $this->gallery->withPhotoIdentityLock($identityKey, function () use ($uploadedPhoto, $action): void {
            $existingPhoto = $this->findPhotoByName($uploadedPhoto->getClientOriginalName());

            if ($existingPhoto !== null && $action === 'replace') {
                $this->replaceCurrentPhotoInGallery($uploadedPhoto, $existingPhoto);

                return;
            }

            if ($existingPhoto !== null) {
                $this->addVersionToGallery($uploadedPhoto, $existingPhoto);

                return;
            }

            $this->addPhotoToGallery($uploadedPhoto);
        });
    }

    /**
     * Return the original file names that would collide with photos already
     * in this gallery, so the uploader can ask how to resolve each conflict.
     *
     * @param  array<int, string>  $fileNames
     * @return array<int, string>
     */
    public function findDuplicateNames(array $fileNames): array
    {
        $identityKeys = collect($fileNames)
            ->mapWithKeys(fn ($name) => [Gallery::identityKey((string) $name) => true]);

        $existingKeys = $this->gallery->photos()
            ->whereIn('identity_key', $identityKeys->keys())
            ->pluck('identity_key')
            ->flip();

        return $this->existingPhotoNames = collect($fileNames)
            ->unique()
            ->filter(fn ($name) => $existingKeys->has(Gallery::identityKey((string) $name)))
            ->values()
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    protected function findPhotoByName(string $clientName): ?Photo
    {
        return $this->gallery->findLogicalPhotoBySourceName($clientName);
    }

    protected function hasSufficientStorage(UploadedFile $uploadedPhoto, int $replacedSize = 0): bool
    {
        $photoSize = max(0, $uploadedPhoto->getSize() - $replacedSize);

        return $this->gallery->team->canStoreFile($photoSize);
    }

    protected function looksLikeAPdf(TemporaryUploadedFile $uploadedPhoto): bool
    {
        $stream = $uploadedPhoto->readStream();

        if (! is_resource($stream)) {
            return false;
        }

        try {
            return fread($stream, 5) === '%PDF-';
        } finally {
            fclose($stream);
        }
    }

    protected function addPhotoToGallery(UploadedFile $uploadedPhoto): void
    {
        $photo = $this->gallery->addPhoto($uploadedPhoto);

        PhotoAdded::dispatch($photo);
    }

    protected function addVersionToGallery(UploadedFile $uploadedPhoto, Photo $existingPhoto): void
    {
        $photo = $this->gallery->addVersion($uploadedPhoto, $existingPhoto);

        PhotoAdded::dispatch($photo);
    }

    protected function replaceCurrentPhotoInGallery(UploadedFile $uploadedPhoto, Photo $existingPhoto): void
    {
        $photo = $this->gallery->replaceCurrentVersion($uploadedPhoto, $existingPhoto);

        PhotoAdded::dispatch($photo);
    }

    public function share()
    {
        $this->shareForm->gallery->is_shared = true;

        if (! $this->team->subscribed()) {
            $this->shareForm->passwordProtected = false;
            $this->shareForm->descriptionEnabled = false;
            $this->shareForm->commentsEnabled = false;
        }

        $this->shareForm->update();

        Flux::modal('share')->close();

        Flux::modal('share-link')->show();

        $this->gallery = $this->gallery->fresh();

        $this->dispatch('gallery-sharing-changed');
    }

    public function disableSharing()
    {
        $this->gallery->update(['is_shared' => false]);

        $this->dispatch('gallery-sharing-changed');
    }

    public function togglePublic()
    {
        $this->gallery->togglePublic();

        $this->dispatch('gallery-public-changed');
    }

    public function deletePhoto(Photo $photo)
    {
        $this->authorize('delete', $photo);

        $photo->deleteFromDisk()->delete();

        $this->getFavorites();
    }

    public function update()
    {
        $this->form->update();

        $this->gallery = $this->gallery->fresh();

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
        $this->favorites = $this->gallery->photosInOrder($this->gallery->favoritePhotoIds());
    }

    public function getCommentedPhotos()
    {
        $this->commentedPhotos = $this->gallery->photosInOrder($this->gallery->commentedPhotoIds());
    }

    public function markFavorites()
    {
        $this->validate([
            'favoriteFileNames' => ['required', 'string'],
        ]);

        $baseNames = collect(explode("\n", $this->favoriteFileNames))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->map(fn ($name) => pathinfo($name, PATHINFO_FILENAME))
            ->filter()
            ->values();

        if ($baseNames->isEmpty()) {
            return;
        }

        $this->gallery->photos()
            ->whereNull('favorited_at')
            ->where(function ($query) use ($baseNames) {
                $baseNames->each(fn ($base) => $query->orWhere('name', 'LIKE', $base.'.%'));
            })
            ->update(['favorited_at' => now()]);

        Cache::forget("gallery:{$this->gallery->id}:favorites:ids");

        $this->getFavorites();
        $this->favoriteFileNames = '';

        Flux::modal('mark-favorites')->close();

        $this->dispatch('photo-favorited');
    }

    #[Computed]
    public function allPhotos()
    {
        return $this->gallery->photosInOrder($this->gallery->photoIds());
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    public bool $showFtpPassword = false;

    #[Computed]
    public function ftpAccount(): ?FtpAccount
    {
        return FtpAccount::where('team_id', $this->gallery->team_id)->first();
    }

    public function toggleFtpPassword()
    {
        $this->showFtpPassword = ! $this->showFtpPassword;
    }

    /**
     * Start or stop routing camera uploads into this gallery. The team's
     * credentials never change, so cameras stay configured.
     */
    public function toggleFtpUploads()
    {
        $this->authorize('update', $this->gallery);

        if (! $this->team->subscribed()) {
            return;
        }

        if (! $account = $this->ftpAccount) {
            $account = FtpAccount::provisionForTeam($this->gallery->team);

            if (! $account) {
                Flux::toast(__('Could not reach the upload server. Please try again.'), variant: 'danger');

                return;
            }
        }

        $account->routeTo($account->routesTo($this->gallery) ? null : $this->gallery);

        unset($this->ftpAccount);
    }
}; ?>

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
                <div class="flex items-center gap-2">
                    @if ($gallery->is_shared)
                        <flux:badge color="lime" size="sm">{{ __('Sharing') }}</flux:badge>
                    @endif

                    @if ($gallery->is_public)
                        <div class="flex items-center gap-1">
                            <flux:badge color="blue" size="sm">{{ __('Public') }}</flux:badge>
                            <flux:tooltip toggleable>
                                <flux:button icon="information-circle" size="xs" variant="subtle" />
                                <flux:tooltip.content class="max-w-[20rem]">
                                    <p>
                                        {{ __('This gallery is public and visible in your portfolio section.') }}
                                    </p>
                                </flux:tooltip.content>
                            </flux:tooltip>
                        </div>
                    @endif
                </div>
            </div>
            <x-subheading class="mt-2">
                {{ __('View, upload, and manage your gallery media.') }}
                @if ($gallery->expiration_date)
                        &bull; {{ __('Expires on') }} {{ $gallery->expiration_date->isoFormat('l') }}
                @endif
            </x-subheading>
            @if ($this->allPhotos?->isNotEmpty())
                <div class="mt-2 text-sm text-zinc-500 dark:text-white/70">
                    {{ $this->allPhotos->count() }}
                    {{ $this->allPhotos->count() === 1 ? __('photo') : __('photos') }} •
                    {{ $gallery->getFormattedStorageSize() }} {{ __('total storage') }}
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

                    @if ($favorites->isNotEmpty())
                        <flux:modal.trigger name="favorite-list">
                            <flux:menu.item icon="heart">{{ __('Favorite list') }}</flux:menu.item>
                        </flux:modal.trigger>
                    @endif

                    <flux:modal.trigger name="live-upload">
                        <flux:menu.item icon="camera">{{ __('Live upload') }}</flux:menu.item>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="edit">
                        <flux:menu.item icon="pencil-square">{{ __('Edit') }}</flux:menu.item>
                    </flux:modal.trigger>

                    <flux:menu.item wire:click="togglePublic" :icon="$gallery->is_public ? 'eye-slash' : 'eye'">
                        {{ $gallery->is_public ? __('Make private') : __('Make public') }}
                    </flux:menu.item>

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
                <flux:button variant="primary">{{ __('Add media') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($this->allPhotos?->isNotEmpty())
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
                @if ($favorites->isNotEmpty())
                    <flux:modal.trigger name="favorite-list">
                        <flux:badge size="sm" as="button">{{ __('Export') }}</flux:badge>
                    </flux:modal.trigger>
                @endif

                <flux:navbar.item
                    @click="$wire.activeTab = 'commented'"
                    x-bind:data-current="$wire.activeTab === 'commented'"
                >
                    {{ __('Commented') }}
                </flux:navbar.item>
            </flux:navbar>

            <div x-show="$wire.activeTab === 'all'" class="pt-1">
                <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($this->allPhotos as $photo)
                        <livewire:photo-item
                            :$photo
                            :key="'photo-'.$photo->id"
                            :html-id="'photo-'.$photo->id"
                            :lazy="$loop->index > 50"
                        />
                    @endforeach
                </div>
            </div>

            <div x-show="$wire.activeTab === 'commented'" class="pt-1">
                <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($commentedPhotos as $photo)
                        <livewire:photo-item
                            :$photo
                            :asCommented="true"
                            :key="'commented-'.$photo->id"
                            :html-id="'commented-'.$photo->id"
                            :lazy="$loop->index > 50"
                        />
                    @endforeach
                </div>
            </div>

            <div x-show="$wire.activeTab === 'favorited'" class="pt-1">
                @if ($favorites->isNotEmpty())
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($favorites as $photo)
                            <livewire:photo-item
                                :$photo
                                :asFavorite="true"
                                :key="'favorite-'.$photo->id"
                                :html-id="'favorite-'.$photo->id"
                                :lazy="$loop->index > 50"
                            />
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-14">
                        <flux:text class="mb-4 max-w-72 text-center">
                            {{ __('No favorites yet. Add them in bulk or click the heart on individual photos.') }}
                        </flux:text>
                        <flux:modal.trigger name="mark-favorites">
                            <flux:button variant="primary" icon="star">
                                {{ __('Add bulk favorites') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
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

    <flux:modal name="add-photos" class="w-full sm:max-w-lg">
        <form class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add media') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload images or videos to your gallery.') }}
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

            @if ($gallery->keep_original_size)
                <flux:callout icon="information-circle" variant="secondary" class="mb-4">
                    <flux:callout.heading>{{ __('Original size enabled') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('There is a maximum photo pixel limit of :max pixels because original size is enabled for this gallery.', ['max' => number_format(config('picstome.max_photo_pixels') / 1000000, 0).'M']) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div
                x-data="multiFileUploader"
                x-on:dragover.prevent="dragActive = true"
                x-on:dragleave.prevent="dragActive = false"
                x-on:drop.prevent="handleDrop($event)"
                :class="{'ring-2 ring-blue-400 ring-offset-4 rounded-sm': dragActive}"
            >
                <!-- File Input -->
                <flux:input
                    @change="handleFileSelect($event)"
                    type="file"
                    accept="{{ '.'.implode(',.', config('picstome.upload_extensions')) }}"
                    multiple
                />
                <flux:description class="mt-2 max-sm:hidden">
                    {{ __('Drag and drop files here, or click on choose files. Supported formats: JPG, JPEG, PNG, TIFF, MP4, WEBM, OGG, PDF, and RAW files (CR2, CR3, NEF, ARW, DNG, ORF, RW2, PEF, SRW, MOS, MRW, 3FR).') }}
                </flux:description>

                <flux:error name="photos" />

                <flux:callout
                    x-show="oversizedFiles.length > 0"
                    x-cloak
                    icon="exclamation-triangle"
                    variant="danger"
                    class="mt-2"
                >
                    <flux:callout.heading>{{ __('Files too large') }}</flux:callout.heading>
                    <flux:callout.text>
                        <p>{{ __('The following files exceed the 5 GB upload limit and were skipped:') }}</p>
                        <ul class="mt-1 list-disc pl-4">
                            <template x-for="(file, i) in oversizedFiles" :key="i">
                                <li x-text="file.name"></li>
                            </template>
                        </ul>
                    </flux:callout.text>
                </flux:callout>

                <flux:callout
                    x-show="unsupportedFiles.length > 0"
                    x-cloak
                    icon="exclamation-triangle"
                    variant="danger"
                    class="mt-2"
                >
                    <flux:callout.heading>{{ __('Unsupported file type') }}</flux:callout.heading>
                    <flux:callout.text>
                        <p>{{ __('The following files have an unsupported format and were skipped:') }}</p>
                        <ul class="mt-1 list-disc pl-4">
                            <template x-for="(file, i) in unsupportedFiles" :key="i">
                                <li x-text="file.name"></li>
                            </template>
                        </ul>
                    </flux:callout.text>
                </flux:callout>

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

                <div
                    x-show="files.some((fileObj) => fileObj.status === 'conflict')"
                    class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span x-text="files.filter((fileObj) => fileObj.status === 'conflict').length + ' ' + {{ Js::from(__('files already exist. Choose what to do, or apply to all:')) }}"></span>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" variant="primary" x-on:click="resolveAllConflicts('version')">
                                {{ __('Version all') }}
                            </flux:button>
                            <flux:button size="sm" variant="subtle" x-on:click="if (confirm({{ Js::from(__('Replace the current versions? Older versions will be kept.')) }})) resolveAllConflicts('replace')">
                                {{ __('Replace all') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" x-on:click="resolveAllConflicts('skip')">
                                {{ __('Skip all') }}
                            </flux:button>
                        </div>
                    </div>
                </div>

                <!-- Upload Queue Display -->
                <div class="space-y-2">
                    <template x-for="(fileObj, index) in files" :key="index">
                        <div
                            x-show="fileObj.status !== 'completed'"
                            class="relative mt-3 flex flex-1 justify-between gap-3 rounded-lg border border-zinc-800/15 bg-white p-4 shadow-xs [--haze-border:color-mix(in_oklab,_var(--color-accent-content),_transparent_80%)] [--haze-light:color-mix(in_oklab,_var(--color-accent),_transparent_98%)] [--haze:color-mix(in_oklab,_var(--color-accent-content),_transparent_97.5%)] *:relative after:pointer-events-none after:absolute after:-inset-px after:rounded-lg hover:border-[var(--haze-border)] hover:after:bg-[var(--haze-light)] dark:border-white/10 dark:bg-white/10 dark:[--haze:color-mix(in_oklab,_var(--color-accent-content),_transparent_90%)] dark:hover:border-white/10 dark:hover:bg-white/15 dark:hover:after:bg-white/[4%]"
                        >
                            <div class="flex w-full flex-wrap items-center gap-4">
                                <div
                                    x-text="fileObj.file.name"
                                    class="flex-1 truncate text-sm font-medium text-zinc-800 dark:text-white"
                                ></div>
                                <div
                                    class="text-sm text-zinc-500 dark:text-white/70"
                                    x-show="['pending', 'queued', 'uploading'].includes(fileObj.status)"
                                >
                                    <span x-text="fileObj.progress + '%'"></span>
                                </div>
                                <flux:badge
                                    x-show="! ['failed', 'conflict', 'skipped'].includes(fileObj.status)"
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
                                    x-show="fileObj.status === 'conflict'"
                                    x-text="{{ Js::from(__('Already exists')) }}"
                                    size="sm"
                                    color="yellow"
                                ></flux:badge>
                                <flux:badge
                                    x-show="fileObj.status === 'skipped'"
                                    x-text="{{ Js::from(__('Skipped')) }}"
                                    size="sm"
                                    color="zinc"
                                ></flux:badge>

                                <div class="flex flex-wrap items-center gap-2" x-show="fileObj.status === 'conflict'">
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        x-on:click="resolveConflict(index, 'version')"
                                    >
                                        {{ __('Save as new version') }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="subtle"
                                        x-on:click="if (confirm({{ Js::from(__('Replace the current version? Older versions will be kept.')) }})) resolveConflict(index, 'replace')"
                                    >
                                        {{ __('Replace current') }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        x-on:click="resolveConflict(index, 'skip')"
                                    >
                                        {{ __('Skip') }}
                                    </flux:button>
                                </div>

                                <flux:button
                                    class="z-10"
                                    x-show="fileObj.status === 'failed'"
                                    x-on:click="retryUpload(index)"
                                    size="sm"
                                    icon="arrow-path"
                                />
                            </div>
                            <div
                                x-show="fileObj.status === 'failed' && fileObj.error"
                                x-text="fileObj.error"
                                class="w-full text-sm text-red-600 dark:text-red-400"
                            ></div>
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

            <flux:switch
                wire:model="shareForm.watermarked"
                :label="__('Watermark photos')"
                @click="$wire.shareForm.downloadable = false"
            />

            <flux:switch
                wire:model="shareForm.downloadable"
                :label="__('Visitors can download photos')"
                x-bind:disabled="$wire.shareForm.watermarked"
            />

            <flux:switch
                wire:model="shareForm.versionsVisible"
                :label="__('Visitors can view and compare versions')"
            />

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

            @if (! $this->team->subscribed())
                <flux:callout icon="bolt" variant="secondary" class="mb-4">
                    <flux:callout.heading>{{ __('Unlock more sharing features') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Subscribe to enable password protection, gallery descriptions, and photo comments for shared galleries.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            <flux:switch
                wire:model="shareForm.passwordProtected"
                :label="__('Protect with a password')"
                :disabled="!$this->team->subscribed()"
            />

            <div x-show="$wire.shareForm.passwordProtected">
                <flux:input
                    wire:model="shareForm.password"
                    type="password"
                    :label="__('Password')"
                    :disabled="!$this->team->subscribed()"
                />
            </div>

            <flux:switch
                wire:model="shareForm.descriptionEnabled"
                :label="__('Add description')"
                :disabled="!$this->team->subscribed()"
            />

            <flux:switch
                wire:model="shareForm.commentsEnabled"
                :label="__('Enable photo comments')"
                :disabled="!$this->team->subscribed()"
            />

            <div x-show="$wire.shareForm.descriptionEnabled">
                <flux:textarea
                    wire:model="shareForm.description"
                    :label="__('Description')"
                    :placeholder="__('Add a description for your shared gallery...')"
                    rows="3"
                />
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
                :value="route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])"
                :label="__('Share URL')"
                readonly
                copyable
            />
        </div>
    </flux:modal>

    <flux:modal name="live-upload" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Live camera upload') }}</flux:heading>
                <flux:subheading>{{ __('Set your camera up once — every shot lands in the gallery receiving uploads.') }}</flux:subheading>
            </div>

            @if (! $this->team->subscribed())
                <flux:callout icon="bolt" variant="secondary">
                    <flux:callout.heading>{{ __('Unlock live camera upload') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Subscribe to shoot live: your camera uploads photos straight to this gallery over FTP/SFTP while you shoot.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            @if ($this->ftpAccount && ! $this->ftpAccount->routesTo($gallery) && $this->ftpAccount->activeGallery)
                <flux:callout
                    icon="exclamation-triangle"
                    variant="warning"
                    :text="__('Photos currently upload to :gallery.', ['gallery' => $this->ftpAccount->activeGallery->name])"
                />
            @endif

            <flux:switch
                wire:click="toggleFtpUploads"
                :checked="$this->ftpAccount?->routesTo($gallery)"
                :label="__('Receive photos in this gallery')"
                :disabled="!$this->team->subscribed()"
            />

            @if ($this->ftpAccount)
                <div class="space-y-4">
                    <flux:input
                        :value="config('picstome.ftp_uploads.host')"
                        :label="__('Host')"
                        readonly
                        copyable
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input
                            :value="config('picstome.ftp_uploads.sftp_port')"
                            :label="__('SFTP port')"
                            readonly
                            copyable
                        />

                        <flux:input
                            :value="config('picstome.ftp_uploads.ftp_port')"
                            :label="__('FTP port')"
                            readonly
                            copyable
                        />
                    </div>

                    <flux:input
                        :value="$this->ftpAccount->username"
                        :label="__('Username')"
                        readonly
                        copyable
                    />

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <div class="flex gap-2">
                            <flux:input
                                class="flex-1"
                                type="{{ $showFtpPassword ? 'text' : 'password' }}"
                                :value="$this->ftpAccount->password"
                                readonly
                                copyable
                            />
                            <flux:button
                                variant="subtle"
                                icon="{{ $showFtpPassword ? 'eye-slash' : 'eye' }}"
                                wire:click="toggleFtpPassword"
                            />
                        </div>
                    </flux:field>

                    @if ($this->ftpAccount->last_upload_at)
                        <flux:callout
                            icon="check-circle"
                            variant="success"
                            :heading="__('Last photo received')"
                            :text="$this->ftpAccount->last_upload_at->diffForHumans()"
                        />
                    @else
                        <flux:callout
                            icon="clock"
                            variant="secondary"
                            :text="__('Waiting for the first photo. It will appear here within a minute of shooting.')"
                        />
                    @endif

                    <div>
                        <flux:heading size="sm">{{ __('Camera setup') }}</flux:heading>

                        <flux:timeline class="mt-2">
                            <flux:timeline.item>
                                <flux:timeline.indicator>
                                    <flux:icon name="wifi" variant="micro" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <flux:heading size="xs">{{ __('Connect to Wi-Fi') }}</flux:heading>
                                    <flux:text size="sm">{{ __('Use a network with internet. A phone hotspot works best.') }}</flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>

                            <flux:timeline.item>
                                <flux:timeline.indicator>
                                    <flux:icon name="server-stack" variant="micro" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <flux:heading size="xs">{{ __('Add the FTP server') }}</flux:heading>
                                    <flux:text size="sm">{{ __('In your camera network menu, add a server with the settings above.') }}</flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>

                            <flux:timeline.item>
                                <flux:timeline.indicator>
                                    <flux:icon name="arrow-up-tray" variant="micro" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <flux:heading size="xs">{{ __('Send JPEGs automatically') }}</flux:heading>
                                    <flux:text size="sm">{{ __('Enable auto send. Shoot RAW+JPEG and send the JPEG only for the fastest uploads.') }}</flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>

                            <flux:timeline.item>
                                <flux:timeline.indicator>
                                    <flux:icon name="camera" variant="micro" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <flux:heading size="xs">{{ __('Take a test shot') }}</flux:heading>
                                    <flux:text size="sm">{{ __('New photos appear in this gallery automatically within a minute.') }}</flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>
                        </flux:timeline>
                    </div>
                </div>
            @endif
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
                <flux:select
                    wire:model="form.photoshoot_id"
                    :label="__('Photoshoot')"
                    :placeholder="__('Choose photoshoot...')"
                >
                    <flux:select.option value="">{{ __('No photoshoot') }}</flux:select.option>
                    <hr />
                    @foreach ($photoshoots as $photoshoot)
                        <flux:select.option value="{{ $photoshoot->id }}">
                            {{ $photoshoot->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input
                wire:model="form.expirationDate"
                :label="__('Expiration date')"
                :badge="__('Optional')"
                type="date"
                clearable
                :disabled="$gallery->is_public"
            />

            @if ($gallery->is_public)
                <flux:callout variant="secondary" icon="information-circle" class="mt-2">
                    <flux:callout.text>
                        {{ __('Public galleries do not expire. You cannot set an expiration date while the gallery is public.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="favorite-list" class="w-full sm:max-w-lg" x-data="copyToClipboard">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Export favorite list') }}</flux:heading>
                <flux:subheading>
                    {{ __(':count medias in your favorite list.', ['count' => $favorites->count()]) }}
                </flux:subheading>
            </div>

            <flux:tab.group x-data="{ tab: 'rater' }">
                <flux:tabs size="sm" class="w-full" scrollable scrollable:fade>
                    <flux:tab name="rater">{{ __('App') }}</flux:tab>
                    <flux:tab name="lightroom">{{ __('Lightroom') }}</flux:tab>
                    <flux:tab name="captureone">{{ __('Capture One') }}</flux:tab>
                    <flux:tab name="finder">{{ __('Finder/Explorer') }}</flux:tab>
                    <flux:tab name="list">{{ __('List') }}</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="rater" class="pt-4!">
                    <flux:text>
                        {{ __('Rate your favorited images with the Picstome Rater desktop app.') }}
                    </flux:text>

                    <div class="mt-6 flex items-center gap-2">
                        @php
                            $rateFiles = $favorites->pluck('name')->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->implode(',');
                        @endphp

                        <flux:button :href="'picstome://rate?files=' . $rateFiles . '&rating=1'" variant="primary">
                            {{ __('Rate my images') }}
                        </flux:button>
                    </div>

                    <flux:text class="mt-2 text-sm">
                        {{ __(':count favorited photos will be rated.', ['count' => $favorites->count()]) }}
                    </flux:text>

                    <flux:text class="mt-1">
                        {{ __("Don't have the app?") }}
                        <flux:link href="https://github.com/picstome/picstome-rater/releases/latest" target="_blank">{{ __('Download Picstome Rater') }}</flux:link>
                    </flux:text>
                </flux:tab.panel>

                <flux:tab.panel name="lightroom" class="pt-4!">
                    <flux:textarea x-ref="lightroom-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(', ', $favorites->pluck('name')->map(function($name) {
                            return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </flux:textarea>

                    <flux:text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </flux:text>

                    <div class="mt-6 flex justify-end">
                        <flux:button variant="primary" size="sm" x-on:click="copy('lightroom-textarea')">
                            {{ __('Copy') }}
                        </flux:button>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="captureone" class="pt-4!">
                    <flux:textarea x-ref="captureone-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(' ', $favorites->pluck('name')->map(function($name) {
                            return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </flux:textarea>

                    <flux:text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </flux:text>

                    <div class="mt-6 flex justify-end">
                        <flux:button variant="primary" size="sm" x-on:click="copy('captureone-textarea')">
                            {{ __('Copy') }}
                        </flux:button>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="finder" class="pt-4!">
                    <flux:textarea x-ref="finder-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(' OR ', $favorites->pluck('name')->map(function($name) {
                            return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </flux:textarea>

                    <flux:text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </flux:text>

                    <div class="mt-6 flex justify-end">
                        <flux:button variant="primary" size="sm" x-on:click="copy('finder-textarea')">
                            {{ __('Copy') }}
                        </flux:button>
                    </div>
                </flux:tab.panel>

                <flux:tab.panel name="list" class="pt-4!">
                    <flux:textarea x-ref="list-textarea" readonly rows="auto" class="max-h-96 min-h-26 text-sm">
                        {{ implode(PHP_EOL, $favorites->pluck('name')->toArray()) }}
                    </flux:textarea>

                    <flux:text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </flux:text>

                    <div class="mt-6 flex justify-end">
                        <flux:button variant="primary" size="sm" x-on:click="copy('list-textarea')">
                            {{ __('Copy') }}
                        </flux:button>
                    </div>
                </flux:tab.panel>
            </flux:tab.group>
        </div>
    </flux:modal>

    <flux:modal name="mark-favorites" class="w-full sm:max-w-lg">
        <form wire:submit="markFavorites" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Mark favorites') }}</flux:heading>
                <flux:subheading>
                    {{ __('Paste a list of filenames to mark them as favorites.') }}
                </flux:subheading>
            </div>

            <flux:textarea
                wire:model="favoriteFileNames"
                :label="__('Filenames')"
                :description:trailing="__('One filename per line.')"
                rows="10"
                class="font-mono text-sm"
            />

            <flux:error name="favoriteFileNames" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Mark as favorites') }}</flux:button>
            </div>
        </form>
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

        Alpine.data('copyToClipboard', () => ({
            copy(refName) {
                const textarea = this.$refs[refName];
                const button = this.$el;
                const originalText = button.textContent;

                if (textarea) {
                    textarea.select();
                    document.execCommand('copy');

                    button.textContent = '{{ __("Copied!") }}';

                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 2000);
                }
            },
        }));

        Alpine.data('multiFileUploader', () => ({
            files: [],
            rawExtensions: @js(\App\Services\PhotoIdentityService::RAW_EXTENSIONS),
            oversizedFiles: [],
            unsupportedFiles: [],
            dragActive: false,
            maxParallelUploads: 10,
            activeUploads: 0,
            maxUploadsPerMinute: 200,
            uploadTimestamps: [],

            init() {
                setInterval(() => {
                    const oneMinuteAgo = Date.now() - 60000;
                    this.uploadTimestamps = this.uploadTimestamps.filter(
                        (timestamp) => timestamp > oneMinuteAgo,
                    );
                    this.processUploadQueue();
                }, 1000);
            },

            async handleFileSelect(event) {
                const selectedFiles = Array.from(event.target.files);
                this.oversizedFiles = [];
                this.unsupportedFiles = [];
                this.processFiles(selectedFiles);
                await this.markConflicts();
                this.processUploadQueue();
            },

            async handleDrop(event) {
                this.dragActive = false;
                this.oversizedFiles = [];
                this.unsupportedFiles = [];
                const dt = event.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    this.processFiles(Array.from(dt.files));
                    await this.markConflicts();
                    this.processUploadQueue();
                }
            },

            canUpload() {
                const oneMinuteAgo = Date.now() - 60000;
                this.uploadTimestamps = this.uploadTimestamps.filter((timestamp) => timestamp > oneMinuteAgo);
                return this.uploadTimestamps.length < this.maxUploadsPerMinute;
            },

            async processUploadQueue() {
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
                    fileObj.status = 'pending';
                    this.uploadFile(fileObj, fileIndex);
                }

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
                        (file) => file.status === 'completed' || file.status === 'skipped',
                    ) && this.activeUploads === 0
                );
            },

            closeIfAllResolved() {
                if (this.checkAllUploadsComplete()) {
                    $flux.modals('add-photos').close();
                }
            },

            async markConflicts() {
                const checkingFiles = this.files.filter((f) => f.status === 'checking');

                if (checkingFiles.length === 0) {
                    return;
                }

                try {
                    const duplicates = await $wire.findDuplicateNames(
                        checkingFiles.map((f) => f.file.name),
                    );

                    checkingFiles.forEach((fileObj) => {
                        const duplicate = duplicates.includes(fileObj.file.name);
                        const extension = fileObj.file.name.split('.').pop().toLowerCase();

                        if (duplicate && this.rawExtensions.includes(extension)) {
                            fileObj.conflictAction = 'version';
                            fileObj.status = 'pending';

                            return;
                        }

                        fileObj.status = duplicate ? 'conflict' : 'pending';
                    });
                } catch (error) {
                    // Fail open: the server still rejects duplicates on save.
                    console.error('Duplicate name check failed:', error);
                    checkingFiles.forEach((fileObj) => {
                        fileObj.status = 'pending';
                    });
                }
            },

            resolveConflict(index, action) {
                const fileObj = this.files[index];

                if (action === 'version' || action === 'replace') {
                    fileObj.conflictAction = action;
                    fileObj.status = 'pending';
                    this.processUploadQueue();

                    return;
                }

                fileObj.status = 'skipped';
                this.closeIfAllResolved();
            },

            resolveAllConflicts(action) {
                this.files.forEach((fileObj) => {
                    if (fileObj.status !== 'conflict') {
                        return;
                    }

                    if (action === 'skip') {
                        fileObj.status = 'skipped';
                    } else {
                        fileObj.conflictAction = action;
                        fileObj.status = 'pending';
                    }
                });

                this.processUploadQueue();
                this.closeIfAllResolved();
            },

            uploadFile(fileObj, index) {
                this.activeUploads++;
                this.uploadTimestamps.push(Date.now());
                fileObj.status = 'uploading';

                const uploadName = `photos.${index}`;

                $wire.upload(
                    uploadName,
                    fileObj.file,
                    () => {
                        $wire.save(index, fileObj.conflictAction || 'version').then((result) => {
                            if (result && result.errors && result.errors['photos.' + index]) {
                                const error = result.errors['photos.' + index];
                                fileObj.error = typeof error === 'string' ? error : null;
                                fileObj.status = 'failed';
                                this.activeUploads--;
                                this.processUploadQueue();
                            } else {
                                fileObj.status = 'completed';
                                fileObj.progress = 100;
                                this.activeUploads--;
                                this.processUploadQueue();
                                this.closeIfAllResolved();
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
                const allowedExtensions = {{ Js::from(config('picstome.upload_extensions')) }};
                const maxSize = 5 * 1024 * 1024 * 1024;

                files.forEach((file) => {
                    const name = file.name;
                    const lastDot = name.lastIndexOf('.');
                    const extension = lastDot !== -1 ? name.substring(lastDot + 1).toLowerCase() : '';

                    if (file.size > maxSize) {
                        this.oversizedFiles.push({ name: file.name, size: file.size });

                        return;
                    }

                    if (!allowedExtensions.includes(extension)) {
                        this.unsupportedFiles.push({ name: file.name });

                        return;
                    }

                    this.files.push({
                        file,
                        progress: 0,
                        status: 'checking',
                    });
                });
            },

            retryUpload(index) {
                const fileObj = this.files[index];
                fileObj.status = 'pending';
                fileObj.progress = 0;
                fileObj.error = null;
                this.processUploadQueue();
            },
        }));
    </script>
@endscript
