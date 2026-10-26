<?php

namespace App\Traits;

/**
 * Summary values shared by the gallery tabs in the owner and shared views.
 *
 * Components using this trait must expose `$gallery`, `$allPhotos` and
 * `$commentedPhotos`.
 */
trait SummarizesGalleryTabs
{
    /**
     * Approximate ZIP size. `stored_size` includes both the processed JPG and
     * the retained RAW file when the gallery keeps originals, matching the
     * files streamed by the gallery download.
     */
    public function totalDownloadSize(): int
    {
        return (int) $this->allPhotos->sum('stored_size');
    }

    public function formattedDownloadSize(): string
    {
        return $this->gallery->formatFileSize($this->totalDownloadSize());
    }

    /**
     * Total comments across the gallery. The commented photos collection is
     * already loaded with comment counts for the comments tab.
     */
    public function totalComments(): int
    {
        return (int) $this->commentedPhotos->sum('comments_count');
    }
}
