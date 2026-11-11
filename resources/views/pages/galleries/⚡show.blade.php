<?php

use App\Events\PhotoAdded;
use App\Livewire\Forms\GalleryForm;
use App\Livewire\Forms\ShareGalleryForm;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\GalleryPhotoQuery;
use App\Services\StorageUsageService;
use App\Traits\InteractsWithTallStackModals;
use App\Traits\RevealsGalleryPhoto;
use App\Traits\SummarizesGalleryTabs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;
    use RevealsGalleryPhoto;
    use SummarizesGalleryTabs, WithFileUploads;

    public Gallery $gallery;

    public GalleryForm $form;

    public ShareGalleryForm $shareForm;

    /** Number of cards rendered per tab. Increased by the "load more" action. */
    public int $allPhotoLimit = 60;

    public bool $adjustingCover = false;

    /**
     * Kept for the shared `RevealsGalleryPhoto` trait, which still knows the
     * legacy `favorited` tab. The owner wall normalises that tab to the
     * favourites filter on All photos before revealing a photo.
     */
    public int $favoritePhotoLimit = 60;

    public int $commentedPhotoLimit = 60;

    public int $archivedPhotoLimit = 60;

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
        $this->remainingStorageBytes = $team->storage_limit === null
            ? null
            : max(0, $team->storage_limit - StorageUsageService::forTeam($team));

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

    /**
     * Store one queued Livewire upload in S3.
     *
     * Large RAW/video uploads can exceed PHP's default max_execution_time
     * while the local temporary file is copied to S3.
     *
     * `#[Renderless]` is deliberate: the uploader only consumes the returned
     * array (id, status, remaining bytes), and re-rendering the 60-tile wall
     * after every single file was the most expensive part of a batch upload.
     * The wall is refreshed once the batch finishes.
     */
    #[Renderless]
    public function save($index, $action = 'version')
    {
        set_time_limit(300);

        if (! isset($this->photos[$index])) {
            return null;
        }

        $action = match ($action) {
            true => 'replace',
            false => 'version',
            'replace' => 'replace',
            'skip' => 'skip',
            default => 'version',
        };

        if ($action === 'skip') {
            return null;
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

                    if ($existingPhoto && $action === 'version' && ! config('picstome.file_versions_enabled')) {
                        $fail(__('File versions are currently unavailable.'));
                    }

                    $replacedSize = $action === 'replace' && $existingPhoto ? ($existingPhoto->stored_size ?: $existingPhoto->size) : 0;

                    if (! $this->hasSufficientStorage($uploadedPhoto, $replacedSize)) {
                        $fail(__('You do not have enough storage space to upload this photo.'));
                    }
                },
            ],
        ]);

        $uploadedPhoto = $this->photos[$index];
        $identityKey = Gallery::identityKey($uploadedPhoto->getClientOriginalName());

        try {
            $photo = $this->gallery->withPhotoIdentityLock($identityKey, function () use ($uploadedPhoto, $action): Photo {
                $existingPhoto = $this->findPhotoByName($uploadedPhoto->getClientOriginalName());

                if ($existingPhoto !== null && $action === 'replace') {
                    return $this->replaceCurrentPhotoInGallery($uploadedPhoto, $existingPhoto);
                }

                if ($existingPhoto !== null) {
                    return $this->addVersionToGallery($uploadedPhoto, $existingPhoto);
                }

                return $this->addPhotoToGallery($uploadedPhoto);
            });
        } catch (\Exception $exception) {
            // The first check runs before the temp file moves to S3, but a
            // concurrent upload can fill the quota between the two checks.
            // Surface it as a field error so the uploader can show it per file.
            if ($exception->getMessage() === 'Not enough storage') {
                throw ValidationException::withMessages([
                    "photos.{$index}" => __('You do not have enough storage space to upload this photo.'),
                ]);
            }

            throw $exception;
        }

        $team = $this->gallery->team;
        $this->remainingStorageBytes = $team->storage_limit === null
            ? null
            : max(0, $team->storage_limit - StorageUsageService::forTeamFresh($team));

        return [
            'photoId' => $photo->id,
            'status' => $photo->status,
            'remainingStorageBytes' => $this->remainingStorageBytes,
        ];
    }

    /**
     * Lightweight status lookup used by the upload dialog while the queue is
     * processing and warming the uploaded photos. Returning only ids and
     * statuses keeps polling cheap and avoids hydrating models.
     *
     * `#[Renderless]` matters as much as the slim payload: without it Livewire
     * re-rendered the whole photo wall (60 tiles, ~810kB of HTML, hundreds of
     * queries) every 2 seconds just to hand back a handful of statuses.
     *
     * @param  array<int, int|string>  $photoIds
     * @return array<int, string>
     */
    #[Renderless]
    public function uploadStatuses(array $photoIds): array
    {
        $ids = collect($photoIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(200)
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return $this->gallery->photos()
            ->whereKey($ids)
            ->pluck('status', 'id')
            ->all();
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

    protected function addPhotoToGallery(UploadedFile $uploadedPhoto): Photo
    {
        $photo = $this->gallery->addPhoto($uploadedPhoto);

        PhotoAdded::dispatch($photo);

        return $photo;
    }

    protected function addVersionToGallery(UploadedFile $uploadedPhoto, Photo $existingPhoto): Photo
    {
        $photo = $this->gallery->addVersion($uploadedPhoto, $existingPhoto);

        PhotoAdded::dispatch($photo);

        return $photo;
    }

    protected function replaceCurrentPhotoInGallery(UploadedFile $uploadedPhoto, Photo $existingPhoto): Photo
    {
        $photo = $this->gallery->replaceCurrentVersion($uploadedPhoto, $existingPhoto);

        PhotoAdded::dispatch($photo);

        return $photo;
    }

    public function share()
    {
        $this->shareForm->gallery->is_shared = true;

        if (! $this->team->subscribed()) {
            $this->shareForm->passwordProtected = false;
            $this->shareForm->descriptionEnabled = false;
            $this->shareForm->commentsEnabled = false;
            $this->shareForm->annotationsEnabled = false;
        }

        $this->shareForm->update();

        $this->closeModal('share');

        $this->openModal('share-link');

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

    public ?int $pendingPhotoId = null;

    #[On('request-delete-photo')]
    public function requestDeletePhoto(int $id): void
    {
        $this->pendingPhotoId = $id;

        $this->openModal('delete-photo');
    }

    public function deletePhoto(Photo $photo)
    {
        $this->authorize('delete', $photo);

        $photo->deleteFromDisk()->delete();

        $this->pendingPhotoId = null;

        $this->forgetPhotoCollections();
    }

    public function toggleFavorite(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $photo->toggleFavorite();

        $this->forgetPhotoCollections();

        $this->dispatch('photo-favorited');
        $this->dispatch(
            'favorite-toggled',
            id: $photo->id,
            favorited: $photo->isFavorited(),
        );
    }

    /**
     * Archive or restore one photo from the gallery grid.
     *
     * Archiving is reversible, so it needs no confirmation: the individual
     * action is instant, exactly like favoriting. Only the destructive
     * "delete all archived" goes through a modal.
     */
    public function toggleArchive(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $this->authorize('update', $this->gallery);

        $photo->toggleArchive();

        // A cover photo that is archived would leak back into the public page.
        if ($photo->isArchived() && $this->gallery->coverPhoto?->is($photo)) {
            $this->gallery->update(['cover_photo_id' => null]);
            $this->gallery->unsetRelation('coverPhoto');
        }

        $this->forgetPhotoCollections();
    }

    /**
     * Ask for confirmation before deleting every archived photo. The modal
     * states that the action is NOT reversible, so it is never auto-run.
     */
    public function requestDeleteArchived(): void
    {
        $this->authorize('update', $this->gallery);

        $this->pendingArchivedCount = $this->archivedIds->count();

        if ($this->pendingArchivedCount === 0) {
            return;
        }

        $this->openModal('delete-archived');
    }

    public function deleteArchived(): void
    {
        $this->authorize('update', $this->gallery);

        $this->gallery->photos()->archived()->with('versions')->cursor()->each(
            fn (Photo $photo) => $photo->deleteFromDisk()->delete()
        );

        $this->pendingArchivedCount = 0;
        $this->activeTab = 'all';

        $this->forgetPhotoCollections();

        $this->closeModal('delete-archived');

        $this->toast()->success(__('All archived photos were deleted.'))->send();
    }

    public function setAsCover(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $this->authorize('updateCover', $this->gallery);

        abort_unless($photo->isImage(), 422);

        $this->gallery->setCoverPhoto($photo);
        $this->gallery->unsetRelation('coverPhoto');
        $this->gallery->load('coverPhoto');

        $this->forgetPhotoCollections();

        $this->dispatch('gallery-cover-changed');
        $this->adjustingCover = true;
    }

    public function removeAsCover(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $this->authorize('updateCover', $this->gallery);

        if ($this->gallery->coverPhoto?->is($photo)) {
            $this->gallery->removeCoverPhoto();
            $this->gallery->load('coverPhoto');

            $this->forgetPhotoCollections();

            $this->dispatch('gallery-cover-changed');
        }
    }

    public function setCoverFocalPoint(float $x, float $y): void
    {
        $this->authorize('updateCover', $this->gallery);

        $this->gallery->setCoverFocalPoint($x, $y);
        $this->gallery->load('coverPhoto');

        $this->forgetPhotoCollections();

        $this->dispatch('gallery-cover-changed');
    }

    public function update()
    {
        $this->form->update();

        $this->gallery = $this->gallery->fresh();

        // The camera upload modal shows the same gallery setting.
        $this->keepOriginalSize = (bool) $this->gallery->keep_original_size;

        $this->closeModal('edit');
    }

    public function delete()
    {
        $this->gallery->deletePhotos()->delete();

        $this->redirect(route('galleries'));
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

    /**
     * Filtering and sorting always restarts paging: the old 60/120/180 windows
     * belong to the previous list and would leak photos that no longer match.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['ratingFilter', 'favoritesOnly', 'sortField', 'sortDirection'], true)) {
            $this->resetPhotoLimits();
        }
    }

    /**
     * Clicking a star filters the wall to exactly that rating; clicking the
     * active star clears the filter.
     */
    public function filterByRating(int $rating): void
    {
        $this->ratingFilter = $this->ratingFilter === $rating ? null : max(1, min(5, $rating));

        $this->resetPhotoLimits();
    }

    public function clearRatingFilter(): void
    {
        $this->ratingFilter = null;

        $this->resetPhotoLimits();
    }

    /**
     * The Favorites tab is a filter on All photos, not a separate panel. It
     * keeps the same grid, sorting and rating filters, and the URL stays
     * shareable as `?favorites=1`.
     */
    public function showFavorites(): void
    {
        $this->activeTab = 'all';
        $this->favoritesOnly = true;

        $this->resetPhotoLimits();
    }

    public function showAllPhotos(): void
    {
        $this->activeTab = 'all';
        $this->favoritesOnly = false;
        $this->ratingFilter = null;

        $this->resetPhotoLimits();
    }

    /**
     * Switch to a real tab. Any tab outside All photos drops the favourites
     * filter, so the filter never leaks into Comments or Archived.
     */
    public function showTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'commented', 'archived', 'sync', 'download'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->favoritesOnly = false;

        $this->resetPhotoLimits();
    }

    public function clearFilters(): void
    {
        $this->ratingFilter = null;
        $this->favoritesOnly = false;

        $this->resetPhotoLimits();
    }

    /**
     * Picking a field opens it in its natural direction (newest/highest first,
     * names A-Z). The arrow next to the dropdown flips it afterwards.
     */
    public function sortBy(string $field): void
    {
        if (! in_array($field, GalleryPhotoQuery::SORTS, true)) {
            return;
        }

        $this->sortField = $field;
        $this->sortDirection = GalleryPhotoQuery::defaultDirection($field);

        $this->resetPhotoLimits();
    }

    public function toggleSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

        $this->resetPhotoLimits();
    }

    protected function resetPhotoLimits(): void
    {
        $this->allPhotoLimit = 60;
        $this->favoritePhotoLimit = 60;
        $this->commentedPhotoLimit = 60;
        $this->archivedPhotoLimit = 60;

        $this->forgetPhotoCollections();
    }

    protected function forgetPhotoCollections(): void
    {
        unset(
            $this->photoIds,
            $this->photoQuery,
            $this->filteredPhotoIds,
            $this->filteredPhotoCount,
            $this->allPhotos,
            $this->favoriteIds,
            $this->favoriteNames,
            $this->commentedIds,
            $this->commentedPhotos,
            $this->archivedIds,
            $this->archivedPhotos,
            $this->totalArchived,
            $this->mediaCounts,
        );

        $this->fallBackFromEmptyArchivedTab();
    }

    /**
     * The Archived tab is rendered only while at least one photo is archived.
     * Restoring the last one hides the panel it was showing, so the grid must
     * switch to All photos instead of rendering an empty tab.
     */
    protected function fallBackFromEmptyArchivedTab(): void
    {
        if ($this->activeTab === 'archived' && $this->archivedIds->isEmpty()) {
            $this->activeTab = 'all';
        }
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
            ->update(['favorited_at' => now(), 'rating' => 5]);

        Cache::forget("gallery:{$this->gallery->id}:favorites:ids");
        Photo::invalidateGalleryQueryCache((int) $this->gallery->id);

        $this->forgetPhotoCollections();
        $this->favoriteFileNames = '';

        $this->closeModal('mark-favorites');

        $this->dispatch('photo-favorited');
    }

    #[Computed]
    public function photoIds(): \Illuminate\Support\Collection
    {
        return $this->gallery->photoIds();
    }

    #[Computed]
    public function photoQuery(): GalleryPhotoQuery
    {
        return GalleryPhotoQuery::fromArray([
            'rating' => $this->ratingFilter,
            'favorites' => $this->favoritesOnly,
            'sort' => $this->sortField,
            'direction' => $this->sortDirection,
        ]);
    }

    /**
     * Stable key for the masonry wrapper. Changing the filter or sort must
     * rebuild the wall (and its Alpine positioning), while rating or hearting
     * a tile must not.
     */
    #[Computed]
    public function queryKey(): string
    {
        return implode('-', [
            $this->ratingFilter ?? 'any',
            $this->favoritesOnly ? 'fav' : 'all',
            $this->sortField,
            $this->sortDirection,
        ]);
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

    /**
     * Ordered ids for the All photos tab, after the active rating/favourite
     * filters and the chosen sort. Cached per gallery version, not per request.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function filteredPhotoIds(): \Illuminate\Support\Collection
    {
        return $this->photoQuery->idsFor($this->gallery, 'all');
    }

    #[Computed]
    public function filteredPhotoCount(): int
    {
        return $this->filteredPhotoIds->count();
    }

    #[Computed]
    public function allPhotos()
    {
        return $this->gallery->photosInOrder($this->filteredPhotoIds->take($this->allPhotoLimit));
    }

    #[Computed]
    public function totalPhotos(): int
    {
        return $this->photoIds->count();
    }

    /**
     * Gallery-wide rated count for the header summary. Unfiltered on purpose:
     * it answers "how much of this shoot did I already cull?".
     *
     * Cached like its neighbours (`photoIds`, `favoriteIds`, `mediaCounts`):
     * this ran `count(*)` on every render, including each 2-second upload
     * poll. `Photo::galleryCacheKeys()` drops the key on every photo write,
     * so rating a photo is reflected immediately.
     */
    #[Computed]
    public function totalRated(): int
    {
        return $this->gallery->ratedPhotoCount();
    }

    #[Computed]
    public function syncEnabled(): bool
    {
        return (bool) Auth::user()?->canSeeSyncFeature();
    }

    #[Computed]
    public function favoriteIds(): \Illuminate\Support\Collection
    {
        return $this->gallery->favoritePhotoIds();
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

    /**
     * Archived photo ids, naturally sorted. Owner-only list: archived photos
     * never reach the public gallery, the favorites or the client ZIP.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    #[Computed]
    public function archivedIds(): \Illuminate\Support\Collection
    {
        return $this->gallery->archivedPhotoIds();
    }

    #[Computed]
    public function archivedPhotos()
    {
        return $this->gallery->photosInOrder($this->archivedIds->take($this->archivedPhotoLimit));
    }

    #[Computed]
    public function totalArchived(): int
    {
        return $this->archivedIds->count();
    }

    public function loadMore(string $tab = 'all'): void
    {
        match ($tab) {
            'commented' => $this->commentedPhotoLimit += 60,
            'archived' => $this->archivedPhotoLimit += 60,
            default => $this->allPhotoLimit += 60,
        };

        $this->forgetPhotoCollections();
    }

    /**
     * Re-render the grid after the live watcher detects a change.
     */
    #[On('gallery-photos-updated')]
    public function refreshPhotos(): void
    {
        $this->forgetPhotoCollections();
        $this->gallery->load(['coverPhoto', 'team']);
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    public bool $showFtpPassword = false;

    /**
     * Mirrors the "keep original size" preference used when creating a
     * gallery, so live sessions honour the last chosen setting.
     */
    public bool $keepOriginalSize = true;

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
                $this->toast()->error(__('Could not reach the upload server. Please try again.'))->send();

                return;
            }
        }

        $routed = $account->routesTo($this->gallery);

        $account->routeTo($routed ? null : $this->gallery);

        if (! $routed) {
            // Live sessions deliver camera originals, so they follow the same
            // preference used when creating a gallery.
            $this->gallery->update(['keep_original_size' => $this->keepOriginalSize]);
        }

        unset($this->ftpAccount);
    }

    /**
     * Live sessions upload camera originals, so the gallery stores them at
     * their original size unless the photographer turns it off. The choice is
     * remembered as the default for the next gallery as well, so the create
     * gallery modal keeps showing the same preference.
     */
    public function updatedKeepOriginalSize(bool $value): void
    {
        $this->authorize('update', $this->gallery);

        $this->gallery->update(['keep_original_size' => $value]);

        // Keep the edit gallery modal in sync so it never shows a stale value.
        $this->form->keepOriginalSize = $value;

        Auth::user()?->rememberGalleryDefaults(['keep_original_size' => $value]);
    }
}; ?>

<div x-data x-on:open-cover-focus.window="$wire.adjustingCover = true">
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
                        :text="__('Favorite list')"
                        icon="heart"
                        x-on:click="show = false; $tsui.open.modal('favorite-list')"
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

    {{-- The tabs also render when everything is archived: the archived tab is
         the only way back to those photos. --}}
    @if ($this->totalPhotos > 0 || $this->totalArchived > 0)
        {{-- Tabs and filter/sort controls share one sticky row, so the
             controls stay reachable while culling a long wall. --}}
        <div class="bg-brand-paper/95 dark:bg-brand-night/95 sticky top-0 z-20 -mx-6 mt-8 px-6 backdrop-blur-md lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-zinc-800/10 pb-1 dark:border-white/20">
                <div class="min-w-0 flex-1">
                    <x-gallery-tabs
                        :favorites-count="$this->totalFavorites"
                        :selection-limit="$gallery->share_selection_limit"
                        :comments-count="$this->totalComments()"
                        :show-sync="$this->syncEnabled"
                        :show-download="true"
                        :archived-count="$this->totalArchived"
                        :active-tab="$activeTab"
                        :favorites-as-filter="true"
                        :favorites-filter-active="$favoritesOnly"
                        :bordered="false"
                    >
                        <x-slot name="afterFavorites">
                            @if ($this->totalFavorites > 0)
                                <x-ts-modal-trigger name="favorite-list">
                                    <x-ts-badge size="sm" as="button">{{ __('Export') }}</x-ts-badge>
                                </x-ts-modal-trigger>
                            @endif
                        </x-slot>
                    </x-gallery-tabs>
                </div>

                <div
                    x-cloak
                    x-show="$wire.activeTab === 'all'"
                    x-transition.opacity.duration.150ms
                    class="shrink-0"
                >
                    <x-gallery-filter-bar
                        :rating="$ratingFilter"
                        :sort-field="$sortField"
                        :sort-direction="$sortDirection"
                        :all-count="$this->filteredPhotoCount"
                        :all-total="$favoritesOnly ? $this->totalFavorites : $this->totalPhotos"
                    />
                </div>
            </div>
        </div>

        <div x-show="$wire.activeTab === 'all'" class="pt-1" data-gallery-panel>
            @if ($this->filteredPhotoCount > 0)
                <div wire:key="all-photos-{{ $this->queryKey }}">
                    <x-photo-masonry :photos="$this->allPhotos" :$gallery context="owner" html-id-prefix="photo" :view-query="$this->photoQuery->toQuery()" />

                    <x-auto-load-more
                        method="loadMore"
                        :args="['all']"
                        :has-more="$this->filteredPhotoCount > $this->allPhotos->count()"
                    />
                </div>
            @elseif ($favoritesOnly)
                <div class="flex flex-col items-center justify-center py-14" data-filter-empty>
                    <span class="bg-brand-star/10 text-brand-star mb-4 grid size-14 place-items-center rounded-2xl">
                        <x-icon name="heart" class="size-7" />
                    </span>

                    @if ($this->totalFavorites === 0)
                        <x-ts-heading size="lg" level="3">{{ __('No favorites yet') }}</x-ts-heading>
                        <x-ts-text class="mt-2 mb-6 max-w-72 text-center">
                            {{ __('Click the heart on any photo, or add a list of filenames in bulk.') }}
                        </x-ts-text>
                        <x-ts-modal-trigger name="mark-favorites">
                            <x-ts-button variant="primary" icon="heart">{{ __('Add bulk favorites') }}</x-ts-button>
                        </x-ts-modal-trigger>
                    @else
                        <x-ts-heading size="lg" level="3">{{ __('No favorites match these filters') }}</x-ts-heading>
                        <x-ts-text class="mt-2 mb-6 max-w-72 text-center">
                            {{ __('Clear the filters to see every favorited photo.') }}
                        </x-ts-text>
                        <x-ts-button wire:click="clearFilters" variant="primary" icon="x-mark">
                            {{ __('Clear filters') }}
                        </x-ts-button>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-14" data-filter-empty>
                    <span class="bg-brand-star/10 text-brand-star mb-4 grid size-14 place-items-center rounded-2xl">
                        <x-icon name="funnel" class="size-7" />
                    </span>
                    <x-ts-heading size="lg" level="3">{{ __('No photos match these filters') }}</x-ts-heading>
                    <x-ts-text class="mt-2 mb-6 max-w-72 text-center">
                        {{ __('Try a different rating or clear the filters to see the whole gallery.') }}
                    </x-ts-text>
                    <x-ts-button wire:click="clearFilters" variant="primary" icon="x-mark">
                        {{ __('Clear filters') }}
                    </x-ts-button>
                </div>
            @endif
        </div>
        <div x-show="$wire.activeTab === 'commented'" class="pt-1" data-gallery-panel>
                <x-photo-masonry :photos="$this->commentedPhotos" :$gallery context="owner" html-id-prefix="commented" :as-commented="true" />

                <x-auto-load-more
                    method="loadMore"
                    :args="['commented']"
                    :has-more="$this->commentedIds->count() > $this->commentedPhotos->count()"
                />
            </div>
        @if ($this->totalArchived > 0)
                <div x-show="$wire.activeTab === 'archived'" class="pt-1" data-gallery-panel>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <x-ts-text class="text-sm">
                            {{ __('Archived photos are hidden from clients and from the gallery download. You can restore them at any time.') }}
                        </x-ts-text>

                        <x-ts-button
                            variant="danger"
                            icon="trash"
                            size="sm"
                            wire:click="requestDeleteArchived"
                            data-delete-archived
                        >
                            {{ __('Delete all archived photos') }}
                        </x-ts-button>
                    </div>

                    <x-photo-masonry :photos="$this->archivedPhotos" :$gallery context="owner" html-id-prefix="archived" :as-archived="true" />

                    <x-auto-load-more
                        method="loadMore"
                        :args="['archived']"
                        :has-more="$this->totalArchived > $this->archivedPhotos->count()"
                    />
                </div>
            @endif
            @if ($this->syncEnabled)
                <div x-show="$wire.activeTab === 'sync'" class="pt-1">
                    <x-gallery-sync-panel :$gallery />
                </div>
            @endif
            <div x-show="$wire.activeTab === 'download'" class="pt-1" data-gallery-panel>
                    <livewire:gallery-download-panel
                        :$gallery
                        :count="$this->totalPhotos"
                        :size="$this->formattedDownloadSize()"
                        :images-count="$this->mediaCounts['images']"
                        :videos-count="$this->mediaCounts['videos']"
                        :pdfs-count="$this->mediaCounts['pdfs']"
                        :key="'download-panel-'.$gallery->id"
                    />
                </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No photos') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any photos. Add one to get started.') }}
            </x-ts-subheading>
            <div class="flex flex-wrap justify-center gap-3">
                <x-ts-modal-trigger name="add-photos">
                    <x-ts-button variant="primary">
                        {{ __('Add media') }}
                    </x-ts-button>
                </x-ts-modal-trigger>

                @if ($this->syncEnabled)
                    <x-ts-button :href="$gallery->syncDeepLink()" variant="outline" icon="arrow-path">
                        {{ __('Sync this gallery') }}
                    </x-ts-button>
                @endif
            </div>
        </div>
    @endif

    <x-ts-modal name="add-photos" class="w-full sm:max-w-lg">
        <form class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Add media') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Upload images or videos to your gallery.') }}
                </x-ts-subheading>
            </div>

            @if (auth()->user()?->currentTeam?->storageUsedPercentage() >= config('picstome.storage_warning_percent', 95))
                <x-ts-callout icon="bolt" variant="secondary">
                    <x-ts-callout-heading>{{ __('Low storage space') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('You have less than 5% storage remaining. ') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Upgrade') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            @if ($gallery->keep_original_size)
                <x-ts-callout icon="information-circle" variant="secondary" class="mb-4">
                    <x-ts-callout-heading>{{ __('Original size enabled') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('There is a maximum photo pixel limit of :max pixels because original size is enabled for this gallery.', ['max' => number_format(config('picstome.max_photo_pixels') / 1000000, 0).'M']) }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            <div
                x-data="multiFileUploader"
                x-on:dragover.window="handleWindowDragOver($event)"
                x-on:dragleave.window="handleWindowDragLeave($event)"
                x-on:drop.window="handleWindowDrop($event)"
                x-on:modal-close.document="if ($event.detail.name === 'add-photos') { resetDrag(); stopStatusPolling(); }"
                x-on:confirm-replace-all.window="confirmReplaceAll()"
                x-on:confirm-replace-conflict.window="confirmReplaceConflict()"
                :class="{'ring-2 ring-(--color-accent) ring-offset-4 rounded-lg': dragActive}"
                id="add-photos-uploader"
            >
                <!-- File Input -->
                <x-ts-input
                    @change="handleFileSelect($event)"
                    type="file"
                    data-test="upload-files"
                    accept="{{ '.'.implode(',.', config('picstome.upload_extensions')) }}"
                    multiple
                />
                <x-ts-description class="mt-2 max-sm:hidden">
                    {{ __('Drag and drop files anywhere on the screen, or click on choose files. Supported formats: JPG, JPEG, PNG, TIFF, MP4, WEBM, OGG, PDF, and RAW files (CR2, CR3, NEF, ARW, DNG, ORF, RW2, PEF, SRW, MOS, MRW, 3FR).') }}
                </x-ts-description>

                <x-ts-error name="photos" />

                <x-ts-callout
                    x-show="oversizedFiles.length > 0"
                    x-cloak
                    icon="exclamation-triangle"
                    variant="danger"
                    class="mt-2"
                >
                    <x-ts-callout-heading>{{ __('Files too large') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        <p>{{ __('The following files exceed the 5 GB upload limit and were skipped:') }}</p>
                        <ul class="mt-1 list-disc pl-4">
                            <template x-for="(file, i) in oversizedFiles" :key="i">
                                <li x-text="file.name"></li>
                            </template>
                        </ul>
                    </x-ts-callout-text>
                </x-ts-callout>

                <x-ts-callout
                    x-show="unsupportedFiles.length > 0"
                    x-cloak
                    icon="exclamation-triangle"
                    variant="danger"
                    class="mt-2"
                >
                    <x-ts-callout-heading>{{ __('Unsupported file type') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        <p>{{ __('The following files have an unsupported format and were skipped:') }}</p>
                        <ul class="mt-1 list-disc pl-4">
                            <template x-for="(file, i) in unsupportedFiles" :key="i">
                                <li x-text="file.name"></li>
                            </template>
                        </ul>
                    </x-ts-callout-text>
                </x-ts-callout>

                <div
                    x-show="files.filter((file) => ! isFileResolved(file)).length > 0"
                    class="mt-4 text-sm font-medium text-zinc-800 dark:text-white"
                >
                    <span x-text="files.filter((file) => ! isFileResolved(file)).length"></span>
                    {{ __('remaining files.') }}
                </div>

                <div
                    x-show="files.some((fileObj) => fileObj.status === 'conflict')"
                    class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span
                            x-text="
                                files.filter((fileObj) => fileObj.status === 'conflict').length +
                                    ' ' +
                                    {{ Js::from(__('files already exist. Choose what to do, or apply to all:')) }}
                            "
                        ></span>
                        <div class="flex flex-wrap gap-2">
                            @if (config('picstome.file_versions_enabled'))
                                <x-ts-button size="sm" variant="primary" x-on:click="resolveAllConflicts('version')">
                                    {{ __('Version all') }}
                                </x-ts-button>
                            @endif

                            <x-ts-button size="sm" variant="subtle" x-on:click="askReplaceAll()">
                                {{ __('Replace all') }}
                            </x-ts-button>
                            <x-ts-button size="sm" variant="ghost" x-on:click="resolveAllConflicts('skip')">
                                {{ __('Skip all') }}
                            </x-ts-button>
                        </div>
                    </div>
                </div>

                <!-- Upload Queue Display -->
                <div class="space-y-2">
                    <template x-for="(fileObj, index) in files" :key="index">
                        <div
                            x-show="isRowVisible(fileObj)"
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
                                <x-ts-badge
                                    x-show="! ['failed', 'conflict', 'skipped'].includes(displayStatus(fileObj))"
                                    x-text="statusLabel(fileObj)"
                                    size="sm"
                                    color="zinc"
                                ></x-ts-badge>
                                <x-ts-badge
                                    x-show="displayStatus(fileObj) === 'failed'"
                                    x-text="statusLabel(fileObj)"
                                    size="sm"
                                    color="red"
                                ></x-ts-badge>
                                <x-ts-badge
                                    x-show="fileObj.status === 'conflict'"
                                    x-text="{{ Js::from(__('Already exists')) }}"
                                    size="sm"
                                    color="yellow"
                                ></x-ts-badge>
                                <x-ts-badge
                                    x-show="fileObj.status === 'skipped'"
                                    x-text="{{ Js::from(__('Skipped')) }}"
                                    size="sm"
                                    color="zinc"
                                ></x-ts-badge>

                                <div
                                    class="flex flex-wrap items-center gap-2"
                                    x-show="fileObj.status === 'conflict'"
                                >
                                    @if (config('picstome.file_versions_enabled'))
                                        <x-ts-button
                                            size="sm"
                                            variant="primary"
                                            x-on:click="resolveConflict(index, 'version')"
                                        >
                                            {{ __('Save as new version') }}
                                        </x-ts-button>
                                    @endif

                                    <x-ts-button size="sm" variant="subtle" x-on:click="askReplaceConflict(index)">
                                        {{ __('Replace current') }}
                                    </x-ts-button>
                                    <x-ts-button
                                        size="sm"
                                        variant="ghost"
                                        x-on:click="resolveConflict(index, 'skip')"
                                    >
                                        {{ __('Skip') }}
                                    </x-ts-button>
                                </div>

                                <x-ts-button
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
    </x-ts-modal>

    <x-ts-modal name="share" class="w-full sm:max-w-lg">
        <form wire:submit="share" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Share gallery') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Customize your shared gallery.') }}</x-ts-subheading>
            </div>

            <x-ts-switch
                wire:model="shareForm.watermarked"
                :label="__('Watermark photos')"
                @click="$wire.shareForm.downloadable = false"
            />

            <x-ts-switch
                wire:model="shareForm.downloadable"
                :label="__('Visitors can download photos')"
                x-bind:disabled="$wire.shareForm.watermarked"
            />

            @if (config('picstome.file_versions_enabled'))
                <x-ts-switch
                    wire:model="shareForm.versionsVisible"
                    :label="__('Visitors can view and compare versions')"
                />
            @endif

            <x-ts-switch wire:model="shareForm.selectable" :label="__('Visitors can select photos')" />

            <div x-show="$wire.shareForm.selectable">
                <x-ts-switch wire:model="shareForm.limitedSelection" :label="__('Limit selection')" />
            </div>

            <div x-show="$wire.shareForm.limitedSelection">
                <x-ts-input wire:model="shareForm.selectionLimit" :label="__('Limit photo selection')" type="number" />
            </div>

            @if (! $this->team->subscribed())
                <x-ts-callout icon="bolt" variant="secondary" class="mb-4">
                    <x-ts-callout-heading>{{ __('Unlock more sharing features') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('Subscribe to enable password protection, gallery descriptions, photo comments and annotations for shared galleries.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            <x-ts-switch
                wire:model="shareForm.passwordProtected"
                :label="__('Protect with a password')"
                :disabled="!$this->team->subscribed()"
            />

            <div x-show="$wire.shareForm.passwordProtected">
                <x-ts-input
                    wire:model="shareForm.password"
                    type="password"
                    :label="__('Password')"
                    :disabled="!$this->team->subscribed()"
                />
            </div>

            <x-ts-switch
                wire:model="shareForm.descriptionEnabled"
                :label="__('Add description')"
                :disabled="!$this->team->subscribed()"
            />

            <x-ts-switch
                wire:model="shareForm.commentsEnabled"
                :label="__('Enable photo comments')"
                :disabled="!$this->team->subscribed()"
                data-test="share-comments-toggle"
                x-on:change="if (! $event.target.checked) $wire.set('shareForm.annotationsEnabled', false)"
            />

            <x-ts-switch
                wire:model="shareForm.annotationsEnabled"
                :label="__('Enable photo annotations')"
                :description="__('Visitors can pin a comment to a spot on the photo. Enabling this also enables photo comments.')"
                :disabled="!$this->team->subscribed()"
                data-test="share-annotations-toggle"
                x-on:change="if ($event.target.checked) $wire.set('shareForm.commentsEnabled', true)"
            />

            <div x-show="$wire.shareForm.descriptionEnabled">
                <x-ts-textarea
                    wire:model="shareForm.description"
                    :label="__('Description')"
                    :placeholder="__('Add a description for your shared gallery...')"
                    rows="3"
                />
            </div>

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="share-link" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <x-ts-heading size="lg">{{ __('Gallery shared') }}</x-ts-heading>

            <x-ts-input
                icon="link"
                :value="route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])"
                :label="__('Share URL')"
                readonly
                copyable
            />
        </div>
    </x-ts-modal>

    <x-ts-modal name="live-upload" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Camera upload') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Set your camera up once — every shot lands in the gallery receiving uploads.') }}
                </x-ts-subheading>
            </div>

            @if (! $this->team->subscribed())
                <x-ts-callout icon="bolt" variant="secondary">
                    <x-ts-callout-heading>{{ __('Unlock camera upload') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('Subscribe to connect your camera: it uploads photos straight to this gallery over FTP/SFTP while you shoot.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Subscribe') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            @if ($this->ftpAccount && ! $this->ftpAccount->routesTo($gallery) && $this->ftpAccount->activeGallery)
                <x-ts-callout
                    icon="exclamation-triangle"
                    variant="warning"
                    :text="__('Photos currently upload to :gallery.', ['gallery' => $this->ftpAccount->activeGallery->name])"
                />
            @endif

            <x-ts-switch
                wire:click="toggleFtpUploads"
                :checked="$this->ftpAccount?->routesTo($gallery)"
                :label="__('Receive photos in this gallery')"
                :disabled="!$this->team->subscribed()"
            />

            <x-ts-switch
                wire:model.live="keepOriginalSize"
                :label="__('Keep photos at their original size')"
                :description="__('Applies to photos uploaded from now on. Photos already uploaded keep their current size.')"
            />

            @if ($this->ftpAccount)
                <div class="space-y-4">
                    <x-ts-input :value="config('picstome.ftp_uploads.host')" :label="__('Host')" readonly copyable />

                    <div class="grid grid-cols-2 gap-4">
                        <x-ts-input
                            :value="config('picstome.ftp_uploads.sftp_port')"
                            :label="__('SFTP port')"
                            readonly
                            copyable
                        />

                        <x-ts-input
                            :value="config('picstome.ftp_uploads.ftp_port')"
                            :label="__('FTP port')"
                            readonly
                            copyable
                        />
                    </div>

                    <x-ts-input :value="$this->ftpAccount->username" :label="__('Username')" readonly copyable />

                    <x-ts-field>
                        <x-ts-label>{{ __('Password') }}</x-ts-label>
                        <div class="flex gap-2">
                            <x-ts-input
                                class="flex-1"
                                type="{{ $showFtpPassword ? 'text' : 'password' }}"
                                :value="$this->ftpAccount->password"
                                readonly
                                copyable
                            />
                            <x-ts-button
                                variant="subtle"
                                icon="{{ $showFtpPassword ? 'eye-slash' : 'eye' }}"
                                wire:click="toggleFtpPassword"
                            />
                        </div>
                    </x-ts-field>

                    @if ($this->ftpAccount->last_upload_at)
                        <x-ts-callout
                            icon="check-circle"
                            variant="success"
                            :heading="__('Last photo received')"
                            :text="$this->ftpAccount->last_upload_at->diffForHumans()"
                        />
                    @else
                        <x-ts-callout
                            icon="clock"
                            variant="secondary"
                            :text="__('Waiting for the first photo. It will appear here within a minute of shooting.')"
                        />
                    @endif

                    <div>
                        <x-ts-heading size="sm">{{ __('Camera setup') }}</x-ts-heading>

                        <x-timeline class="mt-2" compact>
                            <x-timeline.items
                                :title="__('Connect to Wi-Fi')"
                                :description="__('Use a regular Wi-Fi network with internet. A phone hotspot may be slower.')"
                                icon="wifi"
                            />
                            <x-timeline.items
                                :title="__('Add the FTP server')"
                                :description="__('In your camera network menu, add a server with the settings above.')"
                                icon="server-stack"
                            />
                            <x-timeline.items
                                :title="__('Send JPEGs automatically')"
                                :description="__('Enable auto send. Shoot RAW+JPEG and send the JPEG only for the fastest uploads.')"
                                icon="arrow-up-tray"
                            />
                            <x-timeline.items
                                :title="__('Take a test shot')"
                                :description="__('New photos appear in this gallery automatically within a minute.')"
                                icon="camera"
                            />
                        </x-timeline>
                    </div>
                </div>
            @endif
        </div>
    </x-ts-modal>

    <x-ts-modal name="edit" class="w-full sm:max-w-lg">
        <form wire:submit="update" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Edit gallery') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter your gallery details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.name" :label="__('Gallery name')" type="text" />

            @if ($photoshoots)
                <x-ts-select
                    wire:model="form.photoshoot_id"
                    :label="__('Photoshoot')"
                    :placeholder="__('Choose photoshoot...')"
                >
                    <x-ts-select-option value="">{{ __('No photoshoot') }}</x-ts-select-option>
                    <hr />
                    @foreach ($photoshoots as $photoshoot)
                        <x-ts-select-option value="{{ $photoshoot->id }}">
                            {{ $photoshoot->name }}
                        </x-ts-select-option>
                    @endforeach
                </x-ts-select>
            @endif

            <x-ts-input
                wire:model="form.expirationDate"
                :label="__('Expiration date')"
                :badge="$this->team?->subscribed() ? __('Optional') : null"
                type="date"
                clearable
                :disabled="$gallery->is_public"
            />



            <x-ts-switch
                wire:model="form.keepOriginalSize"
                :label="__('Keep photos at their original size')"
                :description="__('Applies to photos uploaded from now on. Photos already uploaded keep their current size.')"
            />

            @if ($gallery->is_public)
                <x-ts-callout variant="secondary" icon="information-circle" class="mt-2">
                    <x-ts-callout-text>
                        {{ __('Public galleries do not expire. You cannot set an expiration date while the gallery is public.') }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    @if ($this->totalFavorites > 0)
        <x-ts-modal name="favorite-list" class="w-full sm:max-w-lg" x-data="copyToClipboard">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Export favorite list') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __(':count medias in your favorite list.', ['count' => $this->totalFavorites]) }}
                </x-ts-subheading>
            </div>

            <x-tab selected="rater" scroll-on-mobile class="w-full" paddingless shadowless>
<x-tab.items tab="rater" :title="__('App')">
                    <x-ts-text>
                        @if ($this->syncEnabled)
                            {{ __('Rate your favorited images with the Picstome Sync desktop app.') }}
                        @else
                            {{ __('Rate your favorited images with the Picstome Rater desktop app.') }}
                        @endif
                    </x-ts-text>

                    <div class="mt-6 flex items-center gap-2">
                        @php
                            $rateFiles = $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->implode(',');
                        @endphp

                        <x-ts-button :href="'picstome://rate?files=' . $rateFiles . '&rating=1'" variant="primary">
                            {{ __('Rate my images') }}
                        </x-ts-button>
                    </div>

                    <x-ts-text class="mt-2 text-sm">
                        {{ __(':count favorited photos will be rated.', ['count' => $this->totalFavorites]) }}
                    </x-ts-text>

                    <x-ts-text class="mt-1">
                        {{ __("Don't have the app?") }}
                        @if ($this->syncEnabled)
                            <x-ts-link href="https://github.com/picstome/picstome-sync/releases/latest" target="_blank">
                                {{ __('Download Picstome Sync') }}
                            </x-ts-link>
                        @else
                            <x-ts-link href="https://github.com/picstome/picstome-rater/releases/latest" target="_blank">
                                {{ __('Download Picstome Rater') }}
                            </x-ts-link>
                        @endif
                    </x-ts-text>
                </x-tab.items>

                <x-tab.items tab="lightroom" :title="__('Lightroom')">
                    <x-ts-textarea id="lightroom-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(', ', $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('lightroom-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>

                <x-tab.items tab="captureone" :title="__('Capture One')">
                    <x-ts-textarea id="captureone-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(' ', $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('captureone-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>

                <x-tab.items tab="finder" :title="__('Finder/Explorer')">
                    <x-ts-textarea id="finder-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(' OR ', $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('finder-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>

                <x-tab.items tab="list" :title="__('List')">
                    <x-ts-textarea id="list-textarea" readonly rows="auto" class="max-h-96 min-h-26 text-sm">
                        {{ implode(PHP_EOL, $this->favoriteNames->toArray()) }}
                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('list-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>
            </x-tab>
        </div>
        </x-ts-modal>
    @endif

    <x-ts-modal name="mark-favorites" class="w-full sm:max-w-lg">
        <form wire:submit="markFavorites" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Mark favorites') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Paste a list of filenames to mark them as favorites.') }}
                </x-ts-subheading>
            </div>

            <x-ts-textarea
                wire:model="favoriteFileNames"
                :label="__('Filenames')"
                :description:trailing="__('One filename per line.')"
                rows="10"
                class="font-mono text-sm"
            />

            <x-ts-error name="favoriteFileNames" />

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Mark as favorites') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-confirm-modal
        name="replace-conflict"
        :title="__('Replace the current version? Older versions will be kept.')"
        alpine-action="$dispatch(`confirm-replace-conflict`)"
        :confirm-label="__('Replace')"
    />

    <x-confirm-modal
        name="replace-conflicts"
        :title="__('Replace the current versions? Older versions will be kept.')"
        alpine-action="$dispatch(`confirm-replace-all`)"
        :confirm-label="__('Replace all')"
    />

    <x-confirm-modal
        name="delete-archived"
        :title="__('Delete all archived photos?')"
        :message="__('This deletes every archived photo and its files. This action CANNOT be undone: the photos and their originals are gone for good.')"
        :confirm-label="__('Delete all archived')"
        method="deleteArchived"
    />

    <x-confirm-modal
        name="delete-gallery"
        :title="__('Delete gallery?')"
        :message="__('This action cannot be undone.')"
        method="delete"
    />

    <x-confirm-modal
        name="delete-photo"
        :title="__('Delete photo?')"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingPhotoId ? 'deletePhoto('.$this->pendingPhotoId.')' : null"
    />

    <x-cover-focus-overlay :gallery="$gallery" :photo="$gallery->coverPhoto" :adjusting="$adjustingCover" />
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

        Alpine.data('multiFileUploader', () => ({
            files: [],
            pendingConflictIndex: null,
            rawExtensions: @js(\App\Services\PhotoIdentityService::RAW_EXTENSIONS),
            oversizedFiles: [],
            unsupportedFiles: [],
            dragActive: false,
            maxParallelUploads: 10,
            activeUploads: 0,
            maxUploadsPerMinute: 200,
            uploadTimestamps: [],
            photoPollTimer: null,
            photoStatusRequestInFlight: false,
            photoStatusPollMs: 2000,
            statusLabels: @js([
                'checking' => __('Checking...'),
                'pending' => __('Queued'),
                'queued' => __('Queued'),
                'uploading' => __('Uploading...'),
                'processing' => __('Processing...'),
                'warming' => __('Warming CDN...'),
                'processed' => __('Ready'),
                'skipped' => __('Skipped'),
                'failed' => __('Failed'),
            ]),

            remainingBytes: @js($this->remainingStorageBytes),

            quotaMessage: @js(__('You do not have enough storage space to upload this photo.')),

            uploadFailedMessage: @js(__('The file could not be uploaded. Please try again.')),

            reservedBytes() {
                return this.files
                    .filter((fileObj) => ['checking', 'pending', 'queued', 'uploading', 'conflict'].includes(fileObj.status))
                    .reduce((total, fileObj) => total + (fileObj.file?.size ?? 0), 0);
            },

            errorMessage(errors) {
                if (! errors) {
                    return null;
                }

                if (typeof errors === 'string') {
                    return errors;
                }

                if (Array.isArray(errors)) {
                    return errors[0] ?? null;
                }

                return null;
            },

            failUpload(fileObj, message) {
                fileObj.error = message;
                fileObj.status = 'failed';
                fileObj.progress = 0;
                this.activeUploads = Math.max(0, this.activeUploads - 1);
                this.processUploadQueue();
                this.closeIfAllResolved();
            },

            init() {
                setInterval(() => {
                    const oneMinuteAgo = Date.now() - 60000;
                    this.uploadTimestamps = this.uploadTimestamps.filter((timestamp) => timestamp > oneMinuteAgo);
                    this.processUploadQueue();
                }, 1000);
            },

            async handleFileSelect(event) {
                const selectedFiles = Array.from(event.target.files);
                this.oversizedFiles = [];
                this.unsupportedFiles = [];
                const added = this.processFiles(selectedFiles);
                await this.markConflicts();
                this.applyStoragePrecheck(added);
                this.processUploadQueue();
            },

            async handleDrop(event) {
                this.dragActive = false;
                this.oversizedFiles = [];
                this.unsupportedFiles = [];
                const dt = event.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    const added = this.processFiles(Array.from(dt.files));
                    await this.markConflicts();
                    this.applyStoragePrecheck(added);
                    this.processUploadQueue();
                }
            },

            isAddPhotosModalOpen() {
                const modal = this.$el.closest('#add-photos');

                return !!modal && modal.offsetParent !== null;
            },

            hasDraggedFiles(event) {
                return Array.from(event.dataTransfer?.types ?? []).includes('Files');
            },

            handleWindowDragOver(event) {
                if (! this.isAddPhotosModalOpen() || ! this.hasDraggedFiles(event)) {
                    return;
                }

                event.preventDefault();
                this.dragActive = true;
            },

            handleWindowDragLeave(event) {
                if (! this.isAddPhotosModalOpen()) {
                    return;
                }

                if (event.relatedTarget === null) {
                    this.dragActive = false;
                }
            },

            async handleWindowDrop(event) {
                if (! this.isAddPhotosModalOpen() || ! this.hasDraggedFiles(event)) {
                    return;
                }

                event.preventDefault();

                await this.handleDrop(event);
            },

            resetDrag() {
                this.dragActive = false;
            },

            canUpload() {
                const oneMinuteAgo = Date.now() - 60000;
                this.uploadTimestamps = this.uploadTimestamps.filter((timestamp) => timestamp > oneMinuteAgo);
                return this.uploadTimestamps.length < this.maxUploadsPerMinute;
            },

            async processUploadQueue() {
                const availableFiles = this.files.filter((f) => f.status === 'pending' || f.status === 'queued');

                while (this.activeUploads < this.maxParallelUploads && availableFiles.length > 0 && this.canUpload()) {
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

            isPhotoStatusTerminal(status) {
                return ['processed', 'skipped', 'failed'].includes(status);
            },

            isFileResolved(fileObj) {
                if (fileObj.status === 'failed' || fileObj.status === 'conflict' || fileObj.status === 'checking') {
                    return false;
                }

                if (fileObj.status === 'completed' && fileObj.photoId) {
                    return this.isPhotoStatusTerminal(fileObj.photoStatus);
                }

                return fileObj.status === 'completed' || fileObj.status === 'skipped';
            },

            isRowVisible(fileObj) {
                return true;
            },

            displayStatus(fileObj) {
                return fileObj.photoStatus || fileObj.status;
            },

            statusLabel(fileObj) {
                return this.statusLabels[this.displayStatus(fileObj)] || this.displayStatus(fileObj);
            },

            checkAllUploadsComplete() {
                return this.files.length > 0
                    && this.files.every((file) => this.isFileResolved(file))
                    && this.activeUploads === 0;
            },

            closeIfAllResolved() {
                if (this.checkAllUploadsComplete()) {
                    this.stopStatusPolling();
                    $tsui.close.modal('add-photos');

                    // Upload actions are `#[Renderless]` so the photo wall is
                    // not re-rendered once per file. Refresh it a single time
                    // once the whole batch has finished.
                    $wire.$refresh();
                }
            },

            startStatusPolling() {
                this.refreshPhotoStatuses();

                if (this.photoPollTimer) {
                    return;
                }

                this.photoPollTimer = setInterval(() => this.refreshPhotoStatuses(), this.photoStatusPollMs);
            },

            stopStatusPolling() {
                if (this.photoPollTimer) {
                    clearInterval(this.photoPollTimer);
                    this.photoPollTimer = null;
                }
            },

            refreshPhotoStatuses() {
                if (! this.isAddPhotosModalOpen()) {
                    this.stopStatusPolling();

                    return;
                }

                const tracked = this.files.filter(
                    (fileObj) => fileObj.photoId && ! this.isPhotoStatusTerminal(fileObj.photoStatus),
                );

                if (tracked.length === 0) {
                    this.stopStatusPolling();
                    this.closeIfAllResolved();

                    return;
                }

                if (this.photoStatusRequestInFlight) {
                    return;
                }

                this.photoStatusRequestInFlight = true;

                $wire.uploadStatuses(tracked.map((fileObj) => fileObj.photoId))
                    .then((statuses) => {
                        this.files.forEach((fileObj) => {
                            if (fileObj.photoId && statuses[fileObj.photoId]) {
                                fileObj.photoStatus = statuses[fileObj.photoId];
                            }
                        });

                        if (this.files.every((fileObj) => ! fileObj.photoId || this.isPhotoStatusTerminal(fileObj.photoStatus))) {
                            this.stopStatusPolling();
                            this.closeIfAllResolved();
                        }
                    })
                    .finally(() => {
                        this.photoStatusRequestInFlight = false;
                    });
            },

            async markConflicts() {
                const checkingFiles = this.files.filter((f) => f.status === 'checking');

                if (checkingFiles.length === 0) {
                    return;
                }

                try {
                    const duplicates = await $wire.findDuplicateNames(checkingFiles.map((f) => f.file.name));

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

            askReplaceAll() {
                window.$tsui.open.modal('replace-conflicts');
            },

            askReplaceConflict(index) {
                this.pendingConflictIndex = index;

                window.$tsui.open.modal('replace-conflict');
            },

            confirmReplaceAll() {
                this.resolveAllConflicts('replace');
            },

            confirmReplaceConflict() {
                if (this.pendingConflictIndex === null) {
                    return;
                }

                this.resolveConflict(this.pendingConflictIndex, 'replace');

                this.pendingConflictIndex = null;
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
                        $wire.save(index, fileObj.conflictAction || 'version')
                            .then((result) => {
                                const validationError = this.errorMessage(result?.errors?.['photos.' + index]);

                                if (validationError) {
                                    this.failUpload(fileObj, validationError);

                                    return;
                                }

                                fileObj.status = 'completed';
                                fileObj.error = null;
                                fileObj.photoId = result?.photoId ?? null;
                                fileObj.photoStatus = result?.status ?? 'pending';
                                fileObj.progress = 100;

                                if (typeof result?.remainingStorageBytes !== 'undefined') {
                                    this.remainingBytes = result.remainingStorageBytes;
                                }

                                this.activeUploads = Math.max(0, this.activeUploads - 1);
                                this.processUploadQueue();

                                if (fileObj.photoId && ! this.isPhotoStatusTerminal(fileObj.photoStatus)) {
                                    this.startStatusPolling();
                                }

                                this.closeIfAllResolved();
                            })
                            .catch((error) => {
                                // Livewire rejects the action promise with
                                // {status: 422, errors: {...}} when the server
                                // validation fails (for example, quota exceeded).
                                const validationError = this.errorMessage(error?.errors?.['photos.' + index])
                                    ?? this.errorMessage(error?.response?.errors?.['photos.' + index]);

                                this.failUpload(fileObj, validationError ?? this.uploadFailedMessage);
                            });
                    },
                    (error) => {
                        console.error('Upload failed:', error);
                        this.failUpload(fileObj, this.uploadFailedMessage);
                    },
                    (event) => {
                        fileObj.progress = Math.round((event.loaded / event.total) * 100);
                    },
                );
            },

            processFiles(files) {
                const allowedExtensions = {{ Js::from(config('picstome.upload_extensions')) }};
                const maxSize = 5 * 1024 * 1024 * 1024;
                const added = [];

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

                    const fileObj = {
                        file,
                        progress: 0,
                        status: 'checking',
                    };

                    this.files.push(fileObj);
                    added.push(file);
                });

                return added;
            },

            applyStoragePrecheck(newFiles) {
                if (this.remainingBytes === null) {
                    return;
                }

                const selected = this.files.filter((fileObj) => newFiles.includes(fileObj.file));

                // Files that are already queued count against the remaining
                // bytes. Conflicts are left to the server because replacing a
                // photo only needs the difference, not the whole new file.
                let reserved = this.files
                    .filter((fileObj) => ! selected.includes(fileObj) && ['checking', 'pending', 'queued', 'uploading', 'conflict'].includes(fileObj.status))
                    .reduce((total, fileObj) => total + (fileObj.file?.size ?? 0), 0);

                selected.forEach((fileObj) => {
                    if (! ['pending', 'queued'].includes(fileObj.status)) {
                        return;
                    }

                    const available = Math.max(0, this.remainingBytes - reserved);

                    if (fileObj.file.size > available) {
                        fileObj.status = 'failed';
                        fileObj.error = this.quotaMessage;
                        fileObj.progress = 0;

                        return;
                    }

                    reserved += fileObj.file.size;
                });
            },

            retryUpload(index) {
                const fileObj = this.files[index];

                if (this.remainingBytes !== null && fileObj.conflictAction !== 'replace' && fileObj.file.size > Math.max(0, this.remainingBytes - this.reservedBytes())) {
                    fileObj.status = 'failed';
                    fileObj.error = this.quotaMessage;

                    return;
                }

                fileObj.status = 'pending';
                fileObj.progress = 0;
                fileObj.error = null;
                fileObj.photoId = null;
                fileObj.photoStatus = null;
                this.processUploadQueue();
            },
        }));
    </script>
@endscript
