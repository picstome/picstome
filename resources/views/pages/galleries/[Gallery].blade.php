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
use Livewire\Attributes\Computed;
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

    public Collection $commentedPhotos;

    public array $existingPhotoNames = [];

    public ?Collection $photoshoots;

    #[Url]
    public $activeTab = 'all';

    public $photos = [];

    public function mount(Gallery $gallery)
    {
        $this->form->setGallery($gallery);
        $this->shareForm->setGallery($gallery);
        $this->getFavorites();
        $this->getCommentedPhotos();
        $this->existingPhotoNames = $gallery->photos()->pluck('name')->toArray();
        $this->photoshoots = Auth::user()?->currentTeam?->photoshoots()->latest()->get();
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

        $this->existingPhotoNames = $this->gallery->photos()->pluck('name')->toArray();
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
        $favorites = $this->gallery->photos()->favorited()->with('gallery')->withCount('comments')->get();

        $this->favorites = $favorites->naturalSortBy('name');
    }

    public function getCommentedPhotos()
    {
        $commented = $this->gallery->photos()
            ->whereHas('comments')
            ->with('gallery')
            ->withCount('comments')
            ->get();

        $this->commentedPhotos = $commented->naturalSortBy('name');
    }

    #[Computed]
    public function allPhotos()
    {
        return $this->gallery->photos()
            ->with('gallery')
            ->withCount('comments')
            ->get()
            ->naturalSortBy('name');
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
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
                                />
                            @endforeach
                        </div>
                    </div>

                    <div x-show="$wire.activeTab === 'favorited'" class="pt-1">
                        <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                            @foreach ($favorites as $photo)
                                <livewire:photo-item
                                    :$photo
                                    :asFavorite="true"
                                    :key="'favorite-'.$photo->id"
                                    :html-id="'favorite-'.$photo->id"
                                />
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
                            accept=".jpg, .jpeg, .png, .tiff, .mp4, .webm, .ogg, .cr2, .cr3, .nef, .arw, .dng, .orf, .rw2, .pef, .srw, .mos, .mrw, .3fr"
                            multiple
                        />
                        <flux:description class="mt-2 max-sm:hidden">
                            {{ __('Drag and drop files here, or click on choose files. Supported formats: JPG, JPEG, PNG, TIFF, MP4, WEBM, OGG, and RAW files (CR2, CR3, NEF, ARW, DNG, ORF, RW2, PEF, SRW, MOS, MRW, 3FR).') }}
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
                                        <div
                                            class="text-sm text-zinc-500 dark:text-white/70"
                                            x-show="fileObj.status !== 'duplicated'"
                                        >
                                            <span x-text="fileObj.progress + '%'"></span>
                                        </div>
                                        <flux:badge
                                            x-show="! ['failed', 'duplicated'].includes(fileObj.status)"
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

                    <flux:tab.group x-data="{ tab: 'lightroom' }">
                        <flux:tabs size="sm" variant="segmented" class="w-full">
                            <flux:tab name="lightroom">{{ __('Lightroom') }}</flux:tab>
                            <flux:tab name="captureone">{{ __('Capture One') }}</flux:tab>
                            <flux:tab name="finder">{{ __('Finder/Explorer') }}</flux:tab>
                        </flux:tabs>

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
                    </flux:tab.group>
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

                Alpine.data('copyToClipboard', () => ({
                    copy(refName) {
                        const textarea = this.$refs[refName];
                        const button = this.$el;
                        const originalText = button.textContent;

                        if (textarea) {
                            textarea.select();
                            document.execCommand('copy');

                            // Change button text temporarily
                            button.textContent = '{{ __("Copied!") }}';

                            // Revert after 2 seconds
                            setTimeout(() => {
                                button.textContent = originalText;
                            }, 2000);
                        }
                    },
                }));

                Alpine.data('multiFileUploader', () => ({
                    files: [],
                    dragActive: false,
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

                    processFiles(files) {
                        const rawExtensions = [
                            'cr2',
                            'cr3',
                            'nef',
                            'arw',
                            'dng',
                            'orf',
                            'rw2',
                            'pef',
                            'srw',
                            'mos',
                            'mrw',
                            '3fr',
                        ];
                        const jpgExtensions = ['jpg', 'jpeg'];
                        const keepOriginalSize = {{ Js::from($gallery->keep_original_size) }};

                        // Group files by base name (without extension)
                        const fileGroups = {};
                        files.forEach((file) => {
                            const name = file.name;
                            const lastDot = name.lastIndexOf('.');
                            const baseName = lastDot !== -1 ? name.substring(0, lastDot) : name;
                            const extension = lastDot !== -1 ? name.substring(lastDot + 1).toLowerCase() : '';

                            if (!fileGroups[baseName]) {
                                fileGroups[baseName] = [];
                            }
                            fileGroups[baseName].push({ file, extension });
                        });

                        // Process each group
                        Object.values(fileGroups).forEach((group) => {
                            const hasJpg = group.some((item) => jpgExtensions.includes(item.extension));
                            const hasRaw = group.some((item) => rawExtensions.includes(item.extension));

                            // If no conflict, add all files
                            if (!hasJpg || !hasRaw) {
                                group.forEach((item) => {
                                    this.files.push({
                                        file: item.file,
                                        progress: 0,
                                        status: 'pending',
                                    });
                                });
                                return;
                            }

                            // Both formats present, decide which to keep
                            if (keepOriginalSize) {
                                // Skip JPG files, keep RAW files
                                group
                                    .filter((item) => rawExtensions.includes(item.extension))
                                    .forEach((item) => {
                                        this.files.push({
                                            file: item.file,
                                            progress: 0,
                                            status: 'pending',
                                        });
                                    });
                            } else {
                                // Skip RAW files, keep JPG files
                                group
                                    .filter((item) => jpgExtensions.includes(item.extension))
                                    .forEach((item) => {
                                        this.files.push({
                                            file: item.file,
                                            progress: 0,
                                            status: 'pending',
                                        });
                                    });
                            }
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
