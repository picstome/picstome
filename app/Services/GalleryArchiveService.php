<?php

namespace App\Services;

use App\Jobs\BuildGalleryArchive;
use App\Jobs\DeleteGalleryArchive;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Builds and manages on-demand gallery ZIP archives.
 *
 * A ZIP is only created when a download is explicitly requested. The archive
 * contains the current processed images, videos and PDFs; RAW files are never
 * included because the processed JPEG already exists on the photo row. Stored
 * archives are invalidated as soon as the gallery content changes.
 */
class GalleryArchiveService
{
    public const VARIANTS = ['all', 'favorites'];

    public function enabled(): bool
    {
        return (bool) config('picstome.gallery_archives.enabled', true);
    }

    public function disk(): string
    {
        return (string) (config('picstome.gallery_archives.disk') ?: config('picstome.disk', 's3'));
    }

    public function ttlDays(): int
    {
        return max(1, (int) config('picstome.gallery_archives.ttl_days', 7));
    }

    public function urlTtlMinutes(): int
    {
        return max(1, (int) config('picstome.gallery_archives.url_ttl_minutes', 60));
    }

    public function findOrCreate(Gallery $gallery, string $variant = 'all', ?User $user = null): GalleryArchive
    {
        $variant = $this->normalizeVariant($variant);
        $fingerprint = $this->fingerprint($gallery, $variant);
        $filename = $this->filename($gallery, $variant);

        $archive = GalleryArchive::query()->createOrFirst(
            [
                'gallery_id' => $gallery->id,
                'variant' => $variant,
                'fingerprint' => $fingerprint,
            ],
            [
                'team_id' => $gallery->team_id,
                'status' => 'pending',
                'disk' => $this->disk(),
                'filename' => $filename,
                'requested_by' => $user?->id,
            ],
        );

        if ($archive->wasRecentlyCreated) {
            $this->dispatchBuild($archive);

            return $archive;
        }

        if ($archive->isReady()) {
            return $archive;
        }

        if (($archive->isPending() || $archive->isBuilding()) && ! $this->isStale($archive)) {
            return $archive;
        }

        if ($archive->path) {
            DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
        }

        $archive->update($this->resetAttributes($gallery, $variant, $user));
        $this->dispatchBuild($archive->refresh());

        return $archive->refresh();
    }

    public function regenerate(GalleryArchive $archive, ?User $user = null): GalleryArchive
    {
        $gallery = $archive->gallery;
        $variant = $archive->variant;

        $this->expire($archive);

        return $this->findOrCreate($gallery, $variant, $user);
    }

    public function fingerprint(Gallery $gallery, string $variant = 'all'): string
    {
        $variant = $this->normalizeVariant($variant);

        $photos = $this->photosQuery($gallery, $variant)
            ->select(['id', 'name', 'path', 'raw_path', 'disk', 'size', 'stored_size', 'status', 'updated_at', 'favorited_at'])
            ->orderBy('id')
            ->get()
            ->map(function (Photo $photo) use ($variant): array {
                $data = [
                    'id' => $photo->id,
                    'name' => $photo->name,
                    'path' => $photo->path,
                    'raw_path' => $photo->raw_path,
                    'disk' => $photo->disk,
                    'size' => (int) $photo->size,
                    'stored_size' => (int) $photo->stored_size,
                    'status' => $photo->status,
                    'updated_at' => $photo->updated_at?->toIso8601String(),
                ];

                if ($variant === 'favorites') {
                    $data['favorited_at'] = $photo->favorited_at?->toIso8601String();
                }

                return $data;
            })
            ->all();

        return hash('sha256', json_encode([
            'content_version' => (int) config('picstome.gallery_archives.content_version', 1),
            'gallery' => [
                'name' => $gallery->name,
                'keep_original_size' => (bool) $gallery->keep_original_size,
            ],
            'photos' => $photos,
        ]));
    }

    /**
     * @return array{count: int, bytes: int}
     */
    public function estimate(Gallery $gallery, string $variant = 'all'): array
    {
        $photos = $this->photosQuery($gallery, $this->normalizeVariant($variant))
            ->select(['id', 'path', 'raw_path', 'size', 'stored_size'])
            ->get();

        return [
            'count' => $photos->count(),
            'bytes' => $this->estimatedSizeForPhotos($photos),
        ];
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
            if (! $photo->isImage() && ! $photo->isVideo() && ! $photo->isPdf()) {
                continue;
            }

            if ($photo->isImage() && $photo->raw_path) {
                $processedSize = max(0, (int) $photo->stored_size - (int) $photo->size);
                $bytes += $processedSize > 0 ? $processedSize : (int) $photo->size;

                continue;
            }

            $bytes += (int) ($photo->stored_size ?: $photo->size);
        }

        return $bytes;
    }

    public function build(GalleryArchive $archive): void
    {
        $archive->refresh();

        if (! in_array($archive->status, ['pending', 'building', 'failed'], true) || $archive->isExpired()) {
            return;
        }

        if ($archive->path) {
            DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
        }

        $gallery = $archive->gallery;

        if (! $gallery) {
            $this->markFailed($archive, 'The gallery no longer exists.');

            return;
        }

        $archive->update([
            'status' => 'building',
            'started_at' => now(),
            'completed_at' => null,
            'expires_at' => null,
            'failure_reason' => null,
            'path' => null,
            'size_bytes' => null,
            'photos_total' => 0,
            'photos_processed' => 0,
            'photos_skipped' => 0,
            'images_count' => 0,
            'videos_count' => 0,
            'pdfs_count' => 0,
        ]);

        $query = $this->photosQuery($gallery, $archive->variant);
        $archive->update(['photos_total' => $query->count()]);

        $maxSize = (int) config('picstome.gallery_archives.max_size_bytes', 0);
        $estimate = $this->estimate($gallery, $archive->variant);

        if ($maxSize > 0 && $estimate['bytes'] > $maxSize) {
            $this->markFailed($archive, 'The gallery is larger than the configured archive limit.');

            return;
        }

        $freeSpace = @disk_free_space(sys_get_temp_dir());

        if ($freeSpace !== false && $estimate['bytes'] > 0 && $estimate['bytes'] > $freeSpace * 0.9) {
            $this->markFailed($archive, 'Not enough temporary disk space to build the archive.');

            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'gallery-archive-');

        if ($tempPath === false) {
            $this->markFailed($archive, 'Could not create a temporary archive file.');

            return;
        }

        $uploadedPath = null;

        try {
            $counts = $this->createZipFile($archive, $tempPath);

            if ($counts === null) {
                return; // Invalidated while building.
            }

            $fresh = GalleryArchive::find($archive->id);

            if (! $fresh || $fresh->status !== 'building') {
                return;
            }

            $archive = $fresh;
            $path = $this->archivePath($archive);
            $uploadedPath = $path;

            Storage::disk($this->disk())->putFileAs(
                dirname($path),
                new File($tempPath),
                basename($path),
                ['mimetype' => 'application/zip'],
            );

            $fresh = GalleryArchive::find($archive->id);

            if (! $fresh || $fresh->status !== 'building') {
                Storage::disk($this->disk())->delete($path);

                return;
            }

            $updated = GalleryArchive::query()
                ->whereKey($fresh->id)
                ->where('status', 'building')
                ->update([
                    'status' => 'ready',
                    'disk' => $this->disk(),
                    'path' => $path,
                    'size_bytes' => filesize($tempPath) ?: null,
                    'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                    'photos_skipped' => $counts['skipped'],
                    'images_count' => $counts['images'],
                    'videos_count' => $counts['videos'],
                    'pdfs_count' => $counts['pdfs'],
                    'completed_at' => now(),
                    'expires_at' => now()->addDays($this->ttlDays()),
                    'failure_reason' => null,
                ]);

            if ($updated === 0) {
                Storage::disk($this->disk())->delete($path);
            }
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                Storage::disk($this->disk())->delete($uploadedPath);
            }

            $fresh = GalleryArchive::find($archive->id);

            if ($fresh && $fresh->status === 'building') {
                GalleryArchive::query()
                    ->whereKey($fresh->id)
                    ->where('status', 'building')
                    ->update([
                        'status' => 'pending',
                        'failure_reason' => Str::limit($exception->getMessage(), 500),
                    ]);
            }

            throw $exception;
        } finally {
            @unlink($tempPath);
        }
    }

    public function temporaryUrl(GalleryArchive $archive): string
    {
        $disk = Storage::disk($archive->disk ?: $this->disk());

        return $disk->temporaryUrl($archive->path, now()->addMinutes($this->urlTtlMinutes()), [
            'ResponseContentType' => 'application/zip',
            'ResponseContentDisposition' => $this->contentDisposition($archive->filename),
        ]);
    }

    public function delete(GalleryArchive $archive, bool $deleteNow = false): bool
    {
        if ($archive->path) {
            if ($deleteNow) {
                if (! $this->deleteObject($archive)) {
                    return false;
                }
            } else {
                DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
            }
        }

        $archive->delete();

        return true;
    }

    public function expire(GalleryArchive $archive, bool $deleteNow = false): void
    {
        $archive->update([
            'status' => 'expired',
            'expires_at' => now(),
            'superseded_at' => $archive->superseded_at ?? now(),
        ]);

        if (! $archive->path) {
            return;
        }

        if ($deleteNow) {
            if (! $this->deleteObject($archive)) {
                DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
            }

            return;
        }

        DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
        $archive->updateQuietly(['path' => null]);
    }

    /**
     * @param  array<int, string>  $variants
     */
    public function invalidate(Gallery $gallery, array $variants = ['all']): void
    {
        $variants = array_values(array_intersect($variants, self::VARIANTS));

        if ($variants === []) {
            return;
        }

        $archives = $gallery->archives()
            ->whereIn('variant', $variants)
            ->whereIn('status', ['pending', 'building', 'ready'])
            ->get();

        foreach ($archives as $archive) {
            try {
                $this->expire($archive);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function deleteForGallery(Gallery $gallery): void
    {
        $archives = $gallery->archives()->get();

        foreach ($archives as $archive) {
            try {
                if ($archive->path) {
                    DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function archivePath(GalleryArchive $archive): string
    {
        return "teams/{$archive->team_id}/galleries/{$archive->gallery->ulid}/archives/{$archive->ulid}.zip";
    }

    public function filename(Gallery $gallery, string $variant = 'all'): string
    {
        $base = Str::slug($gallery->name) ?: 'gallery-'.$gallery->ulid;

        return $base.($variant === 'favorites' ? '-favorites' : '').'.zip';
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    protected function addPhotoToZip(ZipStream $zip, Photo $photo, array &$usedNames): string
    {
        if ($photo->isVideo()) {
            $type = 'video';
            $entryName = $photo->name;
        } elseif ($photo->isPdf()) {
            $type = 'pdf';
            $entryName = $photo->name;
        } elseif ($photo->isImage()) {
            $type = 'image';
            $entryName = $this->imageEntryName($photo);
        } else {
            // RAW-only rows are skipped: the processed JPEG is expected on path.
            return 'skipped';
        }

        $entryName = $this->uniqueEntryName($entryName, $usedNames);
        $stream = null;

        try {
            $stream = Storage::disk($photo->diskOrDefault())->readStream($photo->path);

            if (! is_resource($stream)) {
                return 'skipped';
            }

            $zip->addFileFromStream($entryName, $stream);
        } catch (Throwable $exception) {
            Log::warning('Gallery archive skipped an unreadable file.', [
                'photo_id' => $photo->id,
                'path' => $photo->path,
                'error' => $exception->getMessage(),
            ]);

            return 'skipped';
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $type;
    }

    /**
     * @return array{images: int, videos: int, pdfs: int, skipped: int}|null
     */
    protected function createZipFile(GalleryArchive $archive, string $tempPath): ?array
    {
        $stream = fopen($tempPath, 'wb');

        if ($stream === false) {
            throw new \RuntimeException('Could not open the temporary archive file.');
        }

        $zip = null;
        $finished = false;
        $usedNames = [];
        $counts = ['images' => 0, 'videos' => 0, 'pdfs' => 0, 'skipped' => 0];
        $updatedAt = microtime(true);

        try {
            $zip = new ZipStream(
                outputStream: $stream,
                defaultCompressionMethod: CompressionMethod::STORE,
                sendHttpHeaders: false,
                outputName: $archive->filename,
            );

            $photos = $this->photosQuery($archive->gallery, $archive->variant)->orderBy('id')->cursor();
            $index = 0;

            foreach ($photos as $photo) {
                $index++;

                if ($index % 25 === 1) {
                    $status = GalleryArchive::whereKey($archive->id)->value('status');

                    if ($status !== 'building') {
                        return null;
                    }
                }

                $result = $this->addPhotoToZip($zip, $photo, $usedNames);

                if ($result === 'skipped') {
                    $counts['skipped']++;
                } else {
                    $counts[$result === 'image' ? 'images' : ($result === 'video' ? 'videos' : 'pdfs')]++;
                }

                if ($index % 25 === 0 || microtime(true) - $updatedAt >= 2) {
                    $archive->updateQuietly([
                        'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                        'photos_skipped' => $counts['skipped'],
                        'images_count' => $counts['images'],
                        'videos_count' => $counts['videos'],
                        'pdfs_count' => $counts['pdfs'],
                    ]);
                    $updatedAt = microtime(true);
                }
            }

            $zip->finish();
            $finished = true;

            $archive->updateQuietly([
                'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                'photos_skipped' => $counts['skipped'],
                'images_count' => $counts['images'],
                'videos_count' => $counts['videos'],
                'pdfs_count' => $counts['pdfs'],
            ]);

            return $counts;
        } finally {
            if ($zip && ! $finished) {
                // Do not write a central directory for a cancelled/partial archive.
                unset($zip);
            }

            fclose($stream);
        }
    }

    protected function imageEntryName(Photo $photo): string
    {
        $extension = strtolower(pathinfo($photo->path, PATHINFO_EXTENSION));

        if ($photo->raw_path && in_array($extension, ['jpg', 'jpeg'], true)) {
            return $photo->downloadFileName();
        }

        return $photo->name;
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    protected function uniqueEntryName(string $name, array &$usedNames): string
    {
        $name = basename(str_replace('\\', '/', $name)) ?: 'file';
        $candidate = $name;
        $counter = 1;
        $pathInfo = pathinfo($name);
        $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';
        $base = $pathInfo['filename'] ?? 'file';

        while (in_array(mb_strtolower($candidate), $usedNames, true)) {
            $candidate = $base.' ('.$counter.')'.$extension;
            $counter++;
        }

        $usedNames[] = mb_strtolower($candidate);

        return $candidate;
    }

    protected function photosQuery(Gallery $gallery, string $variant): HasMany
    {
        return $variant === 'favorites' ? $gallery->favorites() : $gallery->photos();
    }

    protected function normalizeVariant(string $variant): string
    {
        return $variant === 'favorites' ? 'favorites' : 'all';
    }

    protected function isStale(GalleryArchive $archive): bool
    {
        $timeout = max(60, (int) config('picstome.gallery_archives.timeout', 3600));
        $threshold = now()->subSeconds($timeout + 300);

        if ($archive->isBuilding()) {
            return $archive->started_at !== null && $archive->started_at->lt($threshold);
        }

        return $archive->created_at !== null && $archive->created_at->lt($threshold);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resetAttributes(Gallery $gallery, string $variant, ?User $user): array
    {
        return [
            'status' => 'pending',
            'disk' => $this->disk(),
            'filename' => $this->filename($gallery, $variant),
            'path' => null,
            'size_bytes' => null,
            'photos_total' => 0,
            'photos_processed' => 0,
            'photos_skipped' => 0,
            'images_count' => 0,
            'videos_count' => 0,
            'pdfs_count' => 0,
            'requested_by' => $user?->id,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
            'superseded_at' => null,
            'storage_deleted_at' => null,
            'failure_reason' => null,
        ];
    }

    protected function dispatchBuild(GalleryArchive $archive): void
    {
        BuildGalleryArchive::dispatch($archive->id)
            ->onQueue((string) config('picstome.gallery_archives.queue', 'default'));
    }

    protected function markFailed(GalleryArchive $archive, string $reason): void
    {
        GalleryArchive::query()
            ->whereKey($archive->id)
            ->whereIn('status', ['pending', 'building'])
            ->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'completed_at' => now(),
            ]);
    }

    protected function deleteObject(GalleryArchive $archive): bool
    {
        if (! $archive->path) {
            return true;
        }

        $disk = Storage::disk($archive->disk ?: $this->disk());
        $path = $archive->path;
        $deleted = false;

        try {
            $deleted = ! $disk->exists($path) || $disk->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($deleted) {
            $archive->updateQuietly([
                'path' => null,
                'storage_deleted_at' => now(),
            ]);
        }

        return $deleted;
    }

    protected function contentDisposition(string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($filename)) ?: 'download.zip';

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            $fallback,
        );
    }
}
