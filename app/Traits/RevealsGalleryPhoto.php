<?php

namespace App\Traits;

use App\Enums\GalleryTab;
use Illuminate\Support\Collection;

/**
 * Expands a paginated gallery grid until the photo linked from the viewer is
 * present in the DOM.
 *
 * The photo viewer returns to `...#photo-{id}`. Fragments never reach the
 * server, so the grid would render only the first batch and the browser would
 * have no target to scroll to. The viewer also appends `?photo={id}`; this
 * trait uses it to grow the limit of the active tab to the required batch.
 *
 * Components using this trait must expose `$activeTab`, the three photo limit
 * properties and the `photoIds`, `favoriteIds` and `commentedIds` computed
 * collections. The owner gallery overrides `revealIds()` so a filtered or
 * sorted wall grows the batch that is actually on screen.
 */
trait RevealsGalleryPhoto
{
    public const PHOTO_PAGE_SIZE = 60;

    protected function revealRequestedPhoto(): void
    {
        $photoId = filter_var(request()->query('photo'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($photoId === false) {
            return;
        }

        $ids = $this->revealIds($this->activeTab);

        $index = $ids->search($photoId);

        if ($index === false) {
            return;
        }

        $requiredLimit = (intdiv($index, self::PHOTO_PAGE_SIZE) + 1) * self::PHOTO_PAGE_SIZE;

        match ($this->activeTab) {
            GalleryTab::Favorited->value => $this->favoritePhotoLimit = max($this->favoritePhotoLimit, $requiredLimit),
            GalleryTab::Commented->value => $this->commentedPhotoLimit = max($this->commentedPhotoLimit, $requiredLimit),
            default => $this->allPhotoLimit = max($this->allPhotoLimit, $requiredLimit),
        };
    }

    /**
     * Ids the reveal-on-return logic should search. The default matches the
     * unfiltered shared gallery; the owner gallery overrides it with the
     * filtered collections.
     *
     * @return Collection<int, int>
     */
    protected function revealIds(string $tab): Collection
    {
        return match ($tab) {
            GalleryTab::Favorited->value => $this->favoriteIds,
            GalleryTab::Commented->value => $this->commentedIds,
            default => $this->photoIds,
        };
    }
}
