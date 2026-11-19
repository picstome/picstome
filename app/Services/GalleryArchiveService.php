<?php

namespace App\Services;

use App\Jobs\BuildGalleryArchive;
use App\Jobs\DeleteGalleryArchive;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Photo;
use App\Models\User;
use App\Support\ContentDisposition;
use App\Traits\Archives\BuildsArchiveZip;
use App\Traits\Archives\EstimatesArchiveSize;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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
    use BuildsArchiveZip;
    use EstimatesArchiveSize;

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

    public function temporaryUrl(GalleryArchive $archive): string
    {
        $disk = Storage::disk($archive->disk ?: $this->disk());

        return $disk->temporaryUrl($archive->path, now()->addMinutes($this->urlTtlMinutes()), [
            'ResponseContentType' => 'application/zip',
            'ResponseContentDisposition' => ContentDisposition::attachment($archive->filename, 'download.zip'),
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

    protected function photosQuery(Gallery $gallery, string $variant): HasMany
    {
        // Archived photos are never shipped: the customer ZIP is exactly the
        // selection the photographer wants them to see.
        return ($variant === 'favorites' ? $gallery->favorites() : $gallery->photos())->ready()->notArchived();
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
}
