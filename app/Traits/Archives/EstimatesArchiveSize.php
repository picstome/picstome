<?php

namespace App\Traits\Archives;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;

/**
 * Approximate archive sizes computed from photo rows, never from S3 HEADs.
 */
trait EstimatesArchiveSize
{
    /**
     * @return array{count: int, bytes: int}
     */
    public function estimate(Gallery $gallery, string $variant = 'all'): array
    {
        $query = $this->photosQuery($gallery, $this->normalizeVariant($variant));

        return [
            'count' => (clone $query)->count(),
            'bytes' => $this->estimatedSizeForPhotos(
                (clone $query)->get(['id', 'path', 'raw_path', 'disk', 'size', 'stored_size'])
            ),
        ];
    }

    /**
     * Approximate ZIP size, answered by one aggregate query over the photo
     * rows. The exact size is only known once the ZIP is built and stored on
     * `gallery_archives.size_bytes`, so nothing here may list or stat objects
     * in the bucket: that turned a 221-photo gallery into ~440 S3 HEADs.
     */
    public function estimatedSize(Gallery $gallery, string $variant = 'all'): int
    {
        return (int) $this->photosQuery($gallery, $this->normalizeVariant($variant))
            ->sum(DB::raw($this->estimatedBytesExpression()));
    }

    /**
     * SQL equivalent of `estimatedSizeForPhoto()`. Thumbnails never travel in
     * the ZIP, so a normal image contributes exactly `size` (the processed
     * file). A gallery that keeps the original RAW stores `size` = RAW and
     * `stored_size` = processed + thumbnail + RAW, so the processed part is
     * `stored_size - size`; that includes the thumbnail and therefore
     * overestimates by a few KB per photo, which is fine for an estimate.
     * Videos and PDFs have no thumbnail copy in the ZIP and use `stored_size`.
     */
    protected function estimatedBytesExpression(): string
    {
        $size = 'COALESCE(size, 0)';
        $stored = 'COALESCE(stored_size, 0)';
        $processed = "CASE WHEN {$stored} > {$size} THEN {$stored} - {$size} ELSE {$size} END";
        $notSupported = "LOWER(path) NOT LIKE '%.jpg' AND LOWER(path) NOT LIKE '%.jpeg' AND LOWER(path) NOT LIKE '%.png'"
            ." AND LOWER(path) NOT LIKE '%.tiff' AND LOWER(path) NOT LIKE '%.mp4' AND LOWER(path) NOT LIKE '%.webm'"
            ." AND LOWER(path) NOT LIKE '%.ogg' AND LOWER(path) NOT LIKE '%.pdf'";
        $isImage = "LOWER(path) LIKE '%.jpg' OR LOWER(path) LIKE '%.jpeg' OR LOWER(path) LIKE '%.png' OR LOWER(path) LIKE '%.tiff'";

        return 'CASE'
            ." WHEN {$notSupported} THEN 0"
            ." WHEN ({$isImage}) AND raw_path IS NOT NULL AND raw_path <> '' THEN {$processed}"
            ." WHEN {$isImage} THEN CASE WHEN {$size} > 0 THEN {$size} ELSE {$stored} END"
            ." ELSE CASE WHEN {$stored} > 0 THEN {$stored} ELSE {$size} END"
            .' END';
    }

    /**
     * Estimated ZIP size for the included processed files. RAW files are
     * excluded; RAW-only rows without a processed JPEG are skipped.
     *
     * @param  iterable<int, Photo>  $photos
     */
    public function estimatedSizeForPhotos(iterable $photos): int
    {
        $bytes = 0;

        foreach ($photos as $photo) {
            $bytes += $this->estimatedSizeForPhoto($photo);
        }

        return $bytes;
    }

    protected function estimatedSizeForPhoto(Photo $photo): int
    {
        if (! $photo->isImage() && ! $photo->isVideo() && ! $photo->isPdf()) {
            return 0;
        }

        if ($photo->isImage() && $photo->raw_path) {
            $processedSize = (int) $photo->stored_size - (int) $photo->size;

            return $processedSize > 0 ? $processedSize : (int) $photo->size;
        }

        return (int) ($photo->size ?: $photo->stored_size);
    }

    /**
     * Worst-case disk needed by the parallel download window: the largest
     * supported files that can be ahead of the sequential ZIP writer.
     */
    protected function downloadBufferBytes(Gallery $gallery, string $variant, int $concurrency): int
    {
        if ($concurrency < 2) {
            return 0;
        }

        return (int) $this->photosQuery($gallery, $variant)
            ->get(['id', 'path', 'raw_path', 'disk', 'size', 'stored_size'])
            ->map(fn (Photo $photo): int => $this->estimatedSizeForPhoto($photo))
            ->filter(fn (int $bytes): bool => $bytes > 0)
            ->sortDesc()
            ->take($concurrency)
            ->sum();
    }
}
