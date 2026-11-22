<?php

namespace App\Services;

use App\Events\PhotoAdded;
use App\Exceptions\GalleryUploadException;
use App\Exceptions\StorageQuotaExceededException;
use App\Models\Gallery;
use App\Models\Photo;
use App\Support\UploadContent;
use Illuminate\Http\UploadedFile;

/**
 * Stores a browser upload in a gallery.
 *
 * The gallery page (Livewire temporary uploads) and the background upload
 * endpoint share this service so quota, naming, PDF and version rules cannot
 * drift between the two entry points.
 */
class GalleryUploadService
{
    public const ACTION_VERSION = 'version';

    public const ACTION_REPLACE = 'replace';

    public const ACTION_SKIP = 'skip';

    /** Mirrors the 5 GB limit advertised by the uploader UI. */
    public const MAX_FILE_BYTES = 5 * 1024 * 1024 * 1024;

    /**
     * Store one file, resolving duplicate names according to `$action`.
     *
     * Returns null only when the caller asked to skip a file.
     *
     * @throws GalleryUploadException
     * @throws \RuntimeException when the file cannot be written to storage
     */
    public function store(Gallery $gallery, UploadedFile $file, string|bool $action = self::ACTION_VERSION): ?Photo
    {
        $action = $this->normalizeAction($action);

        if ($action === self::ACTION_SKIP) {
            return null;
        }

        $existing = $gallery->findLogicalPhotoBySourceName($file->getClientOriginalName());

        $this->assertFileIsAllowed($gallery, $file, $action, $existing);

        $identityKey = Gallery::identityKey($file->getClientOriginalName());

        try {
            $photo = $gallery->withPhotoIdentityLock($identityKey, function () use ($gallery, $file, $action): Photo {
                // Re-read inside the lock: another request may have created
                // the photo between the pre-check and this point.
                $existing = $gallery->findLogicalPhotoBySourceName($file->getClientOriginalName());

                return match (true) {
                    $existing !== null && $action === self::ACTION_REPLACE => $gallery->replaceCurrentVersion($file, $existing),
                    $existing !== null => $gallery->addVersion($file, $existing),
                    default => $gallery->addPhoto($file),
                };
            });
        } catch (StorageQuotaExceededException) {
            throw new GalleryUploadException(
                GalleryUploadException::STORAGE_QUOTA,
                __('You do not have enough storage space to upload this photo.'),
            );
        } catch (\Exception $exception) {
            throw $exception;
        }

        PhotoAdded::dispatch($photo);

        return $photo;
    }

    /**
     * Bytes the team can still store, or null when storage is unlimited.
     */
    public function remainingStorageBytes(Gallery $gallery): ?int
    {
        $team = $gallery->team;

        return $team->remainingStorageBytes(fresh: true);
    }

    /**
     * Source names that already exist in the gallery, using the same identity
     * key as the uploader so "IMG_1.JPG" and "img_1.jpg" collide.
     *
     * @param  array<int, string>  $fileNames
     * @return array<int, string>
     */
    public function duplicateNames(Gallery $gallery, array $fileNames): array
    {
        $names = collect($fileNames)
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values();

        $identityKeys = $names->mapWithKeys(fn ($name) => [Gallery::identityKey($name) => true]);

        $existingKeys = $gallery->photos()
            ->whereIn('identity_key', $identityKeys->keys())
            ->pluck('identity_key')
            ->flip();

        return $names
            ->filter(fn ($name) => $existingKeys->has(Gallery::identityKey($name)))
            ->values()
            ->all();
    }

    /**
     * Normalize the uploader's legacy boolean actions.
     */
    public function normalizeAction(string|bool $action): string
    {
        return match ($action) {
            true, self::ACTION_REPLACE => self::ACTION_REPLACE,
            false, self::ACTION_VERSION => self::ACTION_VERSION,
            self::ACTION_SKIP => self::ACTION_SKIP,
            default => self::ACTION_VERSION,
        };
    }

    /**
     * Checks that only need the name and size, so they can run before any
     * bytes are sent (direct-to-S3 uploads are signed only when they pass).
     *
     * @throws GalleryUploadException
     */
    public function assertCanUpload(Gallery $gallery, string $fileName, int $size, string|bool $action = self::ACTION_VERSION): void
    {
        $action = $this->normalizeAction($action);
        $existing = $gallery->findLogicalPhotoBySourceName($fileName);

        $this->assertNameAndSizeAreAllowed($gallery, $fileName, $size, $action, $existing);
    }

    /**
     * Apply the same rules the Livewire uploader always enforced.
     *
     * @throws GalleryUploadException
     */
    protected function assertFileIsAllowed(Gallery $gallery, UploadedFile $file, string $action, ?Photo $existing): void
    {
        $this->assertNameAndSizeAreAllowed($gallery, $file->getClientOriginalName(), (int) $file->getSize(), $action, $existing);

        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf' && ! $this->looksLikeAPdf($file)) {
            throw new GalleryUploadException(
                GalleryUploadException::INVALID_PDF,
                __('This file is not a valid PDF.'),
            );
        }

        if (in_array($extension, UploadContent::IMAGE_EXTENSIONS, true) && ! UploadContent::isReadableImage($file)) {
            throw new GalleryUploadException(
                GalleryUploadException::INVALID_IMAGE,
                __('This file is not a readable image.'),
            );
        }

        if (in_array($extension, UploadContent::VIDEO_EXTENSIONS, true) && ! UploadContent::isReadableVideo($file)) {
            throw new GalleryUploadException(
                GalleryUploadException::INVALID_VIDEO,
                __('This file is not a readable video.'),
            );
        }
    }

    /**
     * @throws GalleryUploadException
     */
    protected function assertNameAndSizeAreAllowed(Gallery $gallery, string $fileName, int $size, string $action, ?Photo $existing): void
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, config('picstome.upload_extensions'), true)) {
            throw new GalleryUploadException(
                GalleryUploadException::UNSUPPORTED_TYPE,
                __('This file type is not supported.'),
            );
        }

        if (preg_match('#[/\\\\\x00-\x1F\x7F]#', $fileName)) {
            throw new GalleryUploadException(
                GalleryUploadException::INVALID_FILENAME,
                __('The file name contains invalid characters.'),
            );
        }

        if ($size > self::MAX_FILE_BYTES) {
            throw new GalleryUploadException(
                GalleryUploadException::FILE_TOO_LARGE,
                __('The file exceeds the 5 GB upload limit.'),
            );
        }

        if ($existing !== null && $action === self::ACTION_VERSION && ! config('picstome.file_versions_enabled')) {
            throw new GalleryUploadException(
                GalleryUploadException::VERSIONS_DISABLED,
                __('File versions are currently unavailable.'),
            );
        }

        $replacedSize = $action === self::ACTION_REPLACE && $existing
            ? ($existing->stored_size ?: $existing->size)
            : 0;

        if (! $gallery->team->canStoreFile(max(0, $size - $replacedSize))) {
            throw new GalleryUploadException(
                GalleryUploadException::STORAGE_QUOTA,
                __('You do not have enough storage space to upload this photo.'),
            );
        }
    }

    protected function looksLikeAPdf(UploadedFile $file): bool
    {
        return UploadContent::headerBytes($file, 5) === '%PDF-';
    }
}
