<?php

namespace App\Traits;

/**
 * Summary values shared by the gallery tabs in the owner and shared views.
 *
 * Components using this trait must expose `$gallery`.
 */
trait SummarizesGalleryTabs
{
    /**
     * Approximate ZIP size for the downloadable files. RAW files are excluded
     * and the processed JPEG is used when a RAW was retained.
     */
    public function totalDownloadSize(): int
    {
        return $this->gallery->downloadSize();
    }

    public function formattedDownloadSize(): string
    {
        return $this->gallery->formatFileSize($this->totalDownloadSize());
    }

    /**
     * Total comments across the gallery, cached independently from the
     * commented-photo collection so rendering the tab badge stays cheap.
     */
    public function totalComments(): int
    {
        return $this->gallery->commentsCount();
    }
}
