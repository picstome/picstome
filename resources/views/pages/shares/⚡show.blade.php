<?php

use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Traits\SummarizesGalleryTabs;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use SummarizesGalleryTabs;

    public Gallery $gallery;

    public int $allPhotoLimit = 60;

    public int $favoritePhotoLimit = 60;

    public int $commentedPhotoLimit = 60;

    #[Url]
    public $activeTab = 'all';

    /**
     * Whether the photographer is currently routing camera uploads here.
     * The client page only polls for new photos while this is true.
     */
    public bool $remoteMode = false;

    public function mount(Gallery $gallery, string $slug)
    {
        abort_unless($gallery->is_shared, 404);
        abort_if($gallery->slug !== $slug, 404);

        $this->gallery->loadMissing(['coverPhoto', 'team']);

        $this->remoteMode = FtpAccount::query()
            ->where('team_id', $gallery->team_id)
            ->where('active', true)
            ->where('active_gallery_id', $gallery->id)
            ->exists();
    }

    #[On('photo-favorited')]
    public function getFavorites(): void
    {
        $this->forgetPhotoCollections();
    }

    public function getCommentedPhotos(): void
    {
        $this->forgetPhotoCollections();
    }

    public function toggleFavorite(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        abort_unless($this->gallery->is_share_selectable, 403);

        if ($photo->toggleFavoriteAndCheckSelectionLimit()) {
            $this->dispatch('selection-limit-reached');

            $this->gallery->notifyOwnerWhenSelectionLimitReached();
        }

        $this->forgetPhotoCollections();

        $this->dispatch('photo-favorited');
    }

    protected function forgetPhotoCollections(): void
    {
        unset(
            $this->photoIds,
            $this->allPhotos,
            $this->favoriteIds,
            $this->favorites,
            $this->favoriteNames,
            $this->commentedIds,
            $this->commentedPhotos,
            $this->mediaCounts,
            $this->coverImage,
        );
    }

    /**
     * New camera photos were ingested; reload the grid and cover.
     */
    #[On('gallery-photos-updated')]
    public function refreshPhotos(): void
    {
        $this->gallery->load(['coverPhoto', 'team']);

        $this->forgetPhotoCollections();
    }

    /**
     * The photographer stopped the live session, so stop polling this page.
     */
    #[On('gallery-remote-mode-ended')]
    public function stopRemoteMode(): void
    {
        $this->remoteMode = false;
    }

    #[Computed]
    public function coverImage()
    {
        if ($this->gallery->coverPhoto && $this->gallery->coverPhoto->isImage()) {
            return $this->gallery->coverPhoto;
        }

        return $this->gallery->firstImage();
    }

    #[Computed]
    public function photoIds(): \Illuminate\Support\Collection
    {
        return $this->gallery->readyPhotoIds();
    }

    #[Computed]
    public function allPhotos()
    {
        return $this->gallery->photosInOrder($this->photoIds->take($this->allPhotoLimit));
    }

    #[Computed]
    public function totalPhotos(): int
    {
        return $this->photoIds->count();
    }

    #[Computed]
    public function favoriteIds(): \Illuminate\Support\Collection
    {
        return $this->gallery->favoritePhotoIds();
    }

    #[Computed]
    public function favorites()
    {
        return $this->gallery->photosInOrder($this->favoriteIds->take($this->favoritePhotoLimit));
    }

    #[Computed]
    public function totalFavorites(): int
    {
        return $this->favoriteIds->count();
    }

    #[Computed]
    public function favoriteNames(): \Illuminate\Support\Collection
    {
        $names = $this->gallery->photos()
            ->whereKey($this->favoriteIds)
            ->pluck('name', 'id');

        return $this->favoriteIds
            ->map(fn ($id) => $names[$id] ?? null)
            ->filter()
            ->values();
    }

    #[Computed]
    public function commentedIds(): \Illuminate\Support\Collection
    {
        return $this->gallery->commentedPhotoIds();
    }

    #[Computed]
    public function commentedPhotos()
    {
        return $this->gallery->photosInOrder($this->commentedIds->take($this->commentedPhotoLimit));
    }

    #[Computed]
    public function mediaCounts(): array
    {
        return $this->gallery->mediaCounts();
    }

    public function loadMore(string $tab = 'all'): void
    {
        match ($tab) {
            'favorited' => $this->favoritePhotoLimit += 60,
            'commented' => $this->commentedPhotoLimit += 60,
            default => $this->allPhotoLimit += 60,
        };

        $this->forgetPhotoCollections();
    }
};
?>

<x-slot name="font">{{ $gallery->team->brand_font }}</x-slot>

<x-slot name="color">{{ $gallery->team->brand_color }}</x-slot>

<div x-data x-on:selection-limit-reached.window="$tsui.open.modal('selection-limit')" class="h-full">
    @if ($remoteMode)
        <livewire:gallery-live-watcher
            :$gallery
            :scan-inbox="false"
            :stop-when-remote-mode-ends="true"
            :poll-only-when-visible="true"
            :key="'live-watcher-'.$gallery->id"
        />
    @endif

    @if ($this->totalPhotos > 0)
        <div class="relative">
            <a href="{{ route('handle.show', ['handle' => $gallery->team->handle]) }}">
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </a>
        </div>
        @if ($gallery->coverPhoto)
            <div class="relative mt-4 h-[328px] overflow-hidden md:h-[480px] lg:mt-8">
                <img src="{{ $this->coverImage?->cover_image_url }}" class="h-full w-full object-cover" />
            </div>
        @endif
    @else
        <div>
            <a href="{{ route('handle.show', ['handle' => $gallery->team->handle]) }}">
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </a>
        </div>
    @endif
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <div class="flex items-center gap-4">
                <x-ts-heading level="1" size="xl">{{ $gallery->name }}</x-ts-heading>
            </div>

            <div class="mt-3 h-1 w-10 rounded-full bg-(--color-accent)"></div>

            @if ($gallery->share_description)
                <x-ts-subheading class="mt-2">
                    {{ $gallery->share_description }}
                </x-ts-subheading>
            @endif
        </div>
    </div>
    @if ($this->totalPhotos > 0)
        <div class="mt-8">
            <x-gallery-tabs
                :favorites-count="$this->totalFavorites"
                :selection-limit="$gallery->share_selection_limit"
                :comments-count="$this->totalComments()"
                :show-favorites="$gallery->is_share_selectable"
                :show-comments="$gallery->are_comments_enabled"
                :show-download="$gallery->is_share_downloadable"
            />

            <div x-show="$wire.activeTab === 'all'" class="pt-1">
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($this->allPhotos as $photo)
                            <x-photo-tile :$photo :$gallery context="shared" :html-id="'photo-'.$photo->id" :index="$loop->index" />
                        @endforeach
                    </div>

                    <x-auto-load-more
                        method="loadMore"
                        :args="['all']"
                        :has-more="$this->totalPhotos > $this->allPhotos->count()"
                    />
                </div>
            <div x-show="$wire.activeTab === 'commented'" class="pt-1">
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($this->commentedPhotos as $photo)
                            <x-photo-tile :$photo :$gallery context="shared" :as-commented="true" :html-id="'commented-'.$photo->id" :index="$loop->index" />
                        @endforeach
                    </div>

                    <x-auto-load-more
                        method="loadMore"
                        :args="['commented']"
                        :has-more="$this->commentedIds->count() > $this->commentedPhotos->count()"
                    />
                </div>
            <div x-show="$wire.activeTab === 'favorited'" class="pt-1">
                    @if ($this->totalFavorites > 0)
                        <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                            @foreach ($this->favorites as $photo)
                                <x-photo-tile :$photo :$gallery context="shared" :as-favorite="true" :html-id="'favorite-'.$photo->id" :index="$loop->index" />
                            @endforeach
                        </div>

                        <x-auto-load-more
                            method="loadMore"
                            :args="['favorited']"
                            :has-more="$this->totalFavorites > $this->favorites->count()"
                        />
                    @else
                        <div class="flex flex-col items-center justify-center py-14">
                            <x-ts-text class="max-w-72 text-center">
                                {{ __('No favorites yet. Click on the heart icon to select your favorites.') }}
                            </x-ts-text>
                        </div>
                    @endif
                </div>
            @if ($gallery->is_share_downloadable)
            <div x-show="$wire.activeTab === 'download'" class="pt-1">
                    <livewire:gallery-download-panel
                        :$gallery
                        :count="$this->totalPhotos"
                        :size="$this->formattedDownloadSize()"
                        :images-count="$this->mediaCounts['images']"
                        :videos-count="$this->mediaCounts['videos']"
                        :pdfs-count="$this->mediaCounts['pdfs']"
                        :shared="true"
                        :key="'download-panel-'.$gallery->id"
                    />
                </div>
            @endif
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No photos') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __("We couldn't find any photos.") }}
            </x-ts-subheading>
        </div>
    @endif
    @unlesssubscribed($gallery->team)
        <div class="mt-10">
            @include('partials.powered-by')
        </div>
    @endsubscribed

    <x-selection-limit-modal :limit="$gallery->share_selection_limit" />
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
    </script>
@endscript
