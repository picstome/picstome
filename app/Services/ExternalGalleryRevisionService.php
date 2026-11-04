<?php

namespace App\Services;

use App\Models\Gallery;

/**
 * Builds the opaque `rev` cursor exposed by the external API manifest.
 *
 * The cursor intentionally has no schema impact in v1: it is derived from the
 * newest photo timestamp, the gallery timestamp and the photo count. Including
 * the count guarantees that deleting a photo changes the revision even when
 * the removed row was not the newest one.
 */
class ExternalGalleryRevisionService
{
    public function for(Gallery $gallery): string
    {
        $stats = $gallery->photos()
            ->selectRaw('MAX(updated_at) as max_updated_at, COUNT(*) as total')
            ->first();

        $photoTimestamp = $stats?->max_updated_at
            ? strtotime((string) $stats->max_updated_at)
            : 0;

        $galleryTimestamp = $gallery->updated_at?->getTimestamp() ?? 0;
        $count = (int) ($stats->total ?? 0);

        return substr(hash('sha256', max($photoTimestamp, $galleryTimestamp).':'.$count), 0, 20);
    }
}
