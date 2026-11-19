<?php

namespace App\Traits\Gallery;

use App\Services\GalleryPhotoQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Owner photo wall: tabs, filters, sorting, paging and computed collections.
 */
trait ManagesGalleryPhotoWall
{
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
        $this->allPhotoLimit = self::PHOTO_PAGE_SIZE;
        $this->favoritePhotoLimit = self::PHOTO_PAGE_SIZE;
        $this->commentedPhotoLimit = self::PHOTO_PAGE_SIZE;
        $this->archivedPhotoLimit = self::PHOTO_PAGE_SIZE;

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

    #[Computed]
    public function photoIds(): Collection
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
     * Ordered ids for the All photos tab, after the active rating/favourite
     * filters and the chosen sort. Cached per gallery version, not per request.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function filteredPhotoIds(): Collection
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
    public function favoriteIds(): Collection
    {
        return $this->gallery->favoritePhotoIds();
    }

    #[Computed]
    public function totalFavorites(): int
    {
        return $this->favoriteIds->count();
    }

    #[Computed]
    public function favoriteNames(): Collection
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
    public function commentedIds(): Collection
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
     * @return Collection<int, int>
     */
    #[Computed]
    public function archivedIds(): Collection
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
            'commented' => $this->commentedPhotoLimit += self::PHOTO_PAGE_SIZE,
            'archived' => $this->archivedPhotoLimit += self::PHOTO_PAGE_SIZE,
            default => $this->allPhotoLimit += self::PHOTO_PAGE_SIZE,
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
}
