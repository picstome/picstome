<?php

namespace App\Traits;

use App\Services\GalleryArchiveService;

/**
 * Summary values shared by the gallery tabs in the owner and shared views.
 *
 * Components using this trait must expose `$gallery`, `$allPhotos` and
 * `$commentedPhotos`.
 */
trait SummarizesGalleryTabs
{
    /**
     * Approximate ZIP size for the downloadable files. RAW files are excluded
     * and the processed JPEG is used when a RAW was retained.
     */
    public function totalDownloadSize(): int
    {
        return app(GalleryArchiveService::class)->estimatedSizeForPhotos($this->allPhotos);
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
