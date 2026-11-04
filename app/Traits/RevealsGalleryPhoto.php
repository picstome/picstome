<?php

namespace App\Traits;

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
 * collections.
 */
trait RevealsGalleryPhoto
{
    protected function revealRequestedPhoto(): void
    {
        $photoId = filter_var(request()->query('photo'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($photoId === false) {
            return;
        }

        $ids = match ($this->activeTab) {
            'favorited' => $this->favoriteIds,
            'commented' => $this->commentedIds,
            default => $this->photoIds,
        };

        $index = $ids->search($photoId);

        if ($index === false) {
            return;
        }

        $requiredLimit = (intdiv($index, 60) + 1) * 60;

        match ($this->activeTab) {
            'favorited' => $this->favoritePhotoLimit = max($this->favoritePhotoLimit, $requiredLimit),
            'commented' => $this->commentedPhotoLimit = max($this->commentedPhotoLimit, $requiredLimit),
            default => $this->allPhotoLimit = max($this->allPhotoLimit, $requiredLimit),
        };
    }
}
