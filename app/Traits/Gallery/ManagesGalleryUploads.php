<?php

namespace App\Traits\Gallery;

use App\Exceptions\GalleryUploadException;
use App\Services\GalleryUploadService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;

/**
 * Livewire upload entry points for the owner gallery page.
 */
trait ManagesGalleryUploads
{
    /**
     * Store one queued Livewire upload in S3.
     *
     * Kept as the Livewire entry point for the gallery uploader while the
     * browser-facing upload rules live in `GalleryUploadService`, shared with
     * the background HTTP upload endpoint.
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

        $uploads = app(GalleryUploadService::class);

        try {
            $photo = $uploads->store($this->gallery, $this->photos[$index], $action);
        } catch (GalleryUploadException $exception) {
            // Surface per-file errors through Livewire's validation bag so the
            // uploader can show them next to the file name.
            throw ValidationException::withMessages([
                "photos.{$index}" => $exception->getMessage(),
            ]);
        }

        if ($photo === null) {
            return null;
        }

        $this->remainingStorageBytes = $uploads->remainingStorageBytes($this->gallery);

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
        return $this->existingPhotoNames = app(GalleryUploadService::class)
            ->duplicateNames($this->gallery, $fileNames);
    }
}
