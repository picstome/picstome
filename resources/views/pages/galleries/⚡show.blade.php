<?php

use App\Livewire\Forms\GalleryForm;
use App\Livewire\Forms\ShareGalleryForm;
use App\Models\Gallery;
use App\Traits\Gallery\ManagesGalleryPhotos;
use App\Traits\Gallery\ManagesGalleryPhotoWall;
use App\Traits\Gallery\ManagesGallerySharing;
use App\Traits\Gallery\ManagesGalleryUploads;
use App\Traits\InteractsWithTallStackModals;
use App\Traits\RevealsGalleryPhoto;
use App\Traits\SummarizesGalleryTabs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;
    use ManagesGalleryPhotos;
    use ManagesGalleryPhotoWall;
    use ManagesGallerySharing;
    use ManagesGalleryUploads;
    use RevealsGalleryPhoto;
    use SummarizesGalleryTabs, WithFileUploads;

    public Gallery $gallery;

    public GalleryForm $form;

    public ShareGalleryForm $shareForm;

    /** Number of cards rendered per tab. Increased by the "load more" action. */
    public int $allPhotoLimit = self::PHOTO_PAGE_SIZE;

    public bool $adjustingCover = false;

    /**
     * Kept for the shared `RevealsGalleryPhoto` trait, which still knows the
     * legacy `favorited` tab. The owner wall normalises that tab to the
     * favourites filter on All photos before revealing a photo.
     */
    public int $favoritePhotoLimit = self::PHOTO_PAGE_SIZE;

    public int $commentedPhotoLimit = self::PHOTO_PAGE_SIZE;

    public int $archivedPhotoLimit = self::PHOTO_PAGE_SIZE;

    /** Archived photo ids awaiting the "delete all" confirmation. */
    public int $pendingArchivedCount = 0;

    public array $existingPhotoNames = [];

    public ?Collection $photoshoots;

    #[Url]
    public $activeTab = 'all';

    /**
     * Owner-grid filter state. Kept in the URL so a filtered, sorted wall can
     * be bookmarked, survives the round trip to the viewer and is rebuilt
     * identically by the viewer's next/previous navigation.
     */
    #[Url(as: 'rating')]
    public ?int $ratingFilter = null;

    #[Url(as: 'favorites')]
    public bool $favoritesOnly = false;

    #[Url(as: 'sort')]
    public string $sortField = 'name';

    #[Url(as: 'direction')]
    public string $sortDirection = 'asc';

    public $photos = [];

    /** Bytes the team can still store. Null means unlimited storage. */
    public ?int $remainingStorageBytes = null;

    public string $favoriteFileNames = '';

    public function mount(Gallery $gallery)
    {
        $this->authorize('view', $gallery);
        $this->form->setGallery($gallery);
        $this->shareForm->setGallery($gallery);
        $this->gallery->loadMissing(['coverPhoto', 'team']);

        $team = $this->gallery->team;
        $this->remainingStorageBytes = $team->remainingStorageBytes();

        $this->photoshoots = Auth::user()?->currentTeam?->photoshoots()->latest()->get();
        $this->keepOriginalSize = (bool) $this->gallery->keep_original_size;

        // Old links and bookmarks used a dedicated Favorites tab. It is now a
        // filter on All photos, so normalise the URL instead of rendering an
        // empty panel that no longer exists.
        if ($this->activeTab === 'favorited') {
            $this->activeTab = 'all';
            $this->favoritesOnly = true;
        }

        // The Archived tab only exists while something is archived. A stale
        // link (`?activeTab=archived` after a restore, or browser back) would
        // otherwise render an empty gallery, so fall back to All photos.
        $this->fallBackFromEmptyArchivedTab();

        $this->revealRequestedPhoto();

        if (request()->boolean('live')) {
            $this->openModal('live-upload');
        }
    }

    public ?int $pendingPhotoId = null;

    public function update()
    {
        $this->authorize('update', $this->gallery);

        $this->form->update();

        $this->gallery = $this->gallery->fresh();

        // The camera upload modal shows the same gallery setting.
        $this->keepOriginalSize = (bool) $this->gallery->keep_original_size;

        $this->closeModal('edit');
    }

    public function delete()
    {
        $this->authorize('delete', $this->gallery);

        $this->gallery->deletePhotos()->delete();

        $this->redirect(route('galleries'));
    }

    /**
     * Returning from the viewer must grow the filtered batch that is actually
     * rendered, not the unfiltered gallery.
     */
    protected function revealIds(string $tab): \Illuminate\Support\Collection
    {
        return match ($tab) {
            'commented' => $this->commentedIds,
            'archived' => $this->archivedIds,
            default => $this->filteredPhotoIds,
        };
    }

    public bool $showFtpPassword = false;

    /**
     * Mirrors the "keep original size" preference used when creating a
     * gallery, so live sessions honour the last chosen setting.
     */
    public bool $keepOriginalSize = true;
}; ?>

<div
    x-data="galleryDrop"
    data-gallery-id="{{ $gallery->id }}"
    data-gallery-name="{{ $gallery->name }}"
    x-on:open-cover-focus.window="$wire.adjustingCover = true"
    x-on:dragover.window="onDragOver($event)"
    x-on:dragleave.window="onDragLeave($event)"
    x-on:drop.window="onDrop($event)"
>
    {{-- Full-page drop target: files can be dropped anywhere in the gallery,
         without opening the Add media modal first. --}}
    <div
        x-show="dragActive"
        x-cloak
        data-test="upload-drop-overlay"
        class="bg-brand-night/70 fixed inset-0 z-[55] flex items-center justify-center p-6 backdrop-blur-sm"
        role="presentation"
    >
        <div class="rounded-2xl border-2 border-dashed border-white/70 bg-white/10 px-10 py-14 text-center text-white">
            <x-icon name="arrow-up-tray" class="mx-auto size-10" />
            <p class="mt-4 text-lg font-semibold">{{ __('Drop files to upload') }}</p>
            <p class="mt-1 text-sm text-white/80">{{ __('They will be added to this gallery.') }}</p>
        </div>
    </div>

    <livewire:gallery-live-watcher
        :$gallery
        :scan-inbox="true"
        :stop-when-remote-mode-ends="false"
        :poll-only-when-visible="false"
        :key="'live-watcher-'.$gallery->id"
    />

    <div class="max-lg:hidden">
        <x-ts-button :href="route('galleries')" icon="chevron-left" variant="subtle" inset>
            {{ __('Galleries') }}
        </x-ts-button>
    </div>

    @if ($this->remainingStorageBytes !== null && $this->remainingStorageBytes <= 0)
        <x-ts-callout icon="bolt" variant="warning" class="mt-4">
            <x-ts-callout-heading>{{ __('Camera uploads are paused: no storage left') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('Photos sent from the camera are discarded until you free up space or upgrade your plan.') }}
            </x-ts-callout-text>
            <x-slot name="actions">
                <x-ts-button :href="route('subscribe')" variant="primary">
                    {{ __('Upgrade') }}
                </x-ts-button>
            </x-slot>
        </x-ts-callout>
    @endif

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full min-w-0 sm:flex-1">
            <div class="flex flex-wrap items-center gap-4">
                <x-ts-heading level="1" size="xl" class="min-w-0 break-words">{{ $gallery->name }}</x-ts-heading>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($gallery->is_shared)
                        <x-ts-badge color="lime" size="sm">{{ __('Sharing') }}</x-ts-badge>
                    @endif

                    @if ($gallery->is_public)
                        <div class="flex items-center gap-1">
                            <x-ts-badge color="blue" size="sm">{{ __('Public') }}</x-ts-badge>
                            <x-tooltip
                                :text="__('This gallery is public and visible in your portfolio section.')"
                                icon="information-circle"
                                color="zinc"
                                xs
                            />
                        </div>
                    @endif
                </div>
            </div>
            <x-ts-subheading class="mt-2">
                {{ __('View, upload, and manage your gallery media.') }}
                @if ($gallery->expiration_date)
                        &bull; {{ __('Expires on') }} {{ $gallery->expiration_date->isoFormat('l') }}
                @endif
            </x-ts-subheading>
            @if ($this->totalPhotos > 0)
                <div class="mt-2 text-sm text-zinc-500 dark:text-white/70">
                    {{ $this->totalPhotos }}
                    {{ $this->totalPhotos === 1 ? __('photo') : __('photos') }} •
                    @if ($this->totalRated > 0)
                        {{ $this->totalRated }} {{ __('rated') }} •
                    @endif
                    {{ $gallery->getFormattedStorageSize() }} {{ __('total storage') }}
                </div>
            @endif
        </div>
        <div class="flex flex-wrap justify-end gap-4 max-sm:justify-start">
            <x-dropdown position="bottom-end">
                <x-slot:action>
                    <x-ts-button
                        icon="ellipsis-horizontal"
                        variant="subtle"
                        x-on:click="show = ! show"
                        :aria-label="__('Actions')"
                        data-gallery-actions
                    />
                </x-slot:action>

                <x-dropdown.items
                    :text="__('Download')"
                    icon="cloud-arrow-down"
                    wire:click="showTab('download')"
                    x-on:click="show = false"
                />

                @if ($gallery->coverPhoto)
                    <x-dropdown.items
                        :text="__('Adjust Cover Focus')"
                        icon="viewfinder-circle"
                        wire:click="$set('adjustingCover', true)"
                        x-on:click="show = false"
                    />
                    <x-dropdown.items
                        :text="__('Remove as Cover')"
                        icon="x-mark"
                        wire:click="removeAsCover({{ $gallery->coverPhoto->id }})"
                        x-on:click="show = false"
                    />
                @endif

                @if ($this->totalFavorites > 0)
                    <x-dropdown.items
                        :text="__('Export favorites')"
                        icon="arrow-path"
                        x-on:click="show = false; $tsui.open.modal('favorite-list')"
                        data-export-favorites-menu
                    />
                @endif

                <x-dropdown.items
                    :text="__('Camera upload')"
                    icon="camera"
                    x-on:click="show = false; $tsui.open.modal('live-upload')"
                />
                <x-dropdown.items
                    :text="__('Edit')"
                    icon="pencil-square"
                    x-on:click="show = false; $tsui.open.modal('edit')"
                />
                <x-dropdown.items
                    :text="$gallery->is_public ? __('Make private') : __('Make public')"
                    :icon="$gallery->is_public ? 'eye-slash' : 'eye'"
                    wire:click="togglePublic"
                    x-on:click="show = false"
                />
                <x-dropdown.items
                    :text="__('Delete')"
                    icon="trash"
                    x-on:click="show = false; $tsui.open.modal('delete-gallery')"
                    class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                />
            </x-dropdown>

            @if ($gallery->is_shared)
                <x-ts-button-group>
                    <x-ts-button wire:click="disableSharing" variant="primary" class="btn-ink rounded-e-none!">
                        {{ __('Stop sharing') }}
                    </x-ts-button>
                    <x-dropdown position="bottom-end">
                        <x-slot:action>
                            <x-ts-button icon="chevron-down" variant="primary" class="btn-ink -ms-px rounded-s-none!" x-on:click="show = ! show" />
                        </x-slot:action>

                        <x-dropdown.items
                            :text="__('Get share link')"
                            icon="link"
                            x-on:click="show = false; $tsui.open.modal('share-link')"
                        />
                        <x-dropdown.items
                            :text="__('Share settings')"
                            icon="cog"
                            x-on:click="show = false; $tsui.open.modal('share')"
                        />
                    </x-dropdown>
                </x-ts-button-group>
            @else
                <x-ts-modal-trigger name="share">
                    <x-ts-button variant="primary" class="btn-ink" data-test="open-share-modal">{{ __('Share') }}</x-ts-button>
                </x-ts-modal-trigger>
            @endif

            <x-ts-modal-trigger name="add-photos">
                <x-ts-button variant="primary">{{ __('Add media') }}</x-ts-button>
            </x-ts-modal-trigger>
        </div>
    </div>

    @include('pages.galleries.partials.photo-wall')

    @include('pages.galleries.partials.modals.add-photos')
    @include('pages.galleries.partials.modals.share')
    @include('pages.galleries.partials.modals.live-upload')
    @include('pages.galleries.partials.modals.edit')
    @include('pages.galleries.partials.modals.favorites')
    @include('pages.galleries.partials.modals.confirmations')
</div>

@script
    <script>
        @include('partials.photo-masonry-script')

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

                    button.textContent = '{{ __('Copied!') }}';

                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 2000);
                }
            },
        }));

        Alpine.data('galleryDrop', () => ({
            dragActive: false,

            hasFiles(event) {
                return Array.from(event.dataTransfer?.types ?? []).includes('Files');
            },

            onDragOver(event) {
                if (! this.hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                this.dragActive = true;
            },

            onDragLeave(event) {
                if (event.relatedTarget === null) {
                    this.dragActive = false;
                }
            },

            onDrop(event) {
                if (! this.hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                this.dragActive = false;

                const files = event.dataTransfer?.files;
                const galleryId = this.$el.dataset.galleryId;

                if (files && files.length > 0 && galleryId) {
                    window.PicstomeUploads.enqueue(files, {
                        galleryId,
                        galleryName: this.$el.dataset.galleryName,
                    });
                }
            },
        }));
    </script>
@endscript
