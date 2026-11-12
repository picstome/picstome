<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use App\Services\GalleryArchiveService;
use App\Services\PhotoUrlService;
use App\Services\StorageUsageService;
use App\Traits\FormatsFileSize;
use App\Traits\HasDownloadFilename;
use Carbon\Carbon;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use FormatsFileSize, HasDownloadFilename, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_WARMING = 'warming';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    /** Statuses that may be rendered to gallery visitors. */
    public const READY_STATUSES = [self::STATUS_PROCESSED, self::STATUS_SKIPPED];

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'stored_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'version_count' => 'integer',
            'uploaded_at' => 'datetime',
            'favorited_at' => 'datetime',
            'rating' => 'integer',
            'archived_at' => 'datetime',
            'status' => 'string',
        ];
    }

    /**
     * Every cached gallery listing and summary. One list so a new key cannot
     * be forgotten in one of the three model events below.
     *
     * @return list<string>
     */
    public static function galleryCacheKeys(int $galleryId): array
    {
        return [
            "gallery:{$galleryId}:first_image",
            "gallery:{$galleryId}:photos_count",
            "gallery:{$galleryId}:photos:ids",
            "gallery:{$galleryId}:photos:ready:ids",
            "gallery:{$galleryId}:photos:ready:image:ids",
            "gallery:{$galleryId}:favorites:ids",
            "gallery:{$galleryId}:commented:ids",
            "gallery:{$galleryId}:media_counts",
            "gallery:{$galleryId}:download_size",
            "gallery:{$galleryId}:comments_count",
            "gallery:{$galleryId}:rated_count",
        ];
    }

    protected static function forgetGalleryCaches(int $galleryId): void
    {
        foreach (static::galleryCacheKeys($galleryId) as $key) {
            Cache::forget($key);
        }

        static::bumpGalleryCacheVersion($galleryId);
    }

    /**
     * Monotonic stamp for the owner grid's filtered and sorted listings.
     *
     * Those listings are cached per filter/sort combination, so they cannot be
     * enumerated in `galleryCacheKeys()`. Bumping this stamp makes every old
     * combination unreachable at once; the stale entries expire on their own.
     */
    public static function galleryCacheVersion(int $galleryId): int
    {
        return (int) Cache::get("gallery:{$galleryId}:cache_version", 1);
    }

    protected static function bumpGalleryCacheVersion(int $galleryId): void
    {
        Cache::forever(
            "gallery:{$galleryId}:cache_version",
            static::galleryCacheVersion($galleryId) + 1,
        );
    }

    /**
     * Public escape hatch for bulk writes that bypass model events (for
     * example marking a pasted filename list as favourites). Any filtered or
     * sorted listing cached for this gallery becomes stale immediately.
     */
    public static function invalidateGalleryQueryCache(int $galleryId): void
    {
        static::bumpGalleryCacheVersion($galleryId);
    }

    protected static function booted()
    {
        static::creating(function (Photo $photo) {
            if (empty($photo->identity_key)) {
                $photo->identity_key = Gallery::identityKey($photo->source_name ?: $photo->name);
            }

            if (! $photo->stored_size) {
                $photo->stored_size = $photo->size;
            }
        });

        static::created(function (Photo $photo) {
            static::forgetGalleryCaches((int) $photo->gallery_id);
            StorageUsageService::forgetForGallery($photo->gallery_id);

            static::invalidateGalleryArchives($photo, ['all']);
        });

        static::updated(function (Photo $photo) {
            $contentFields = ['name', 'source_name', 'path', 'raw_path', 'disk', 'size', 'stored_size', 'status', 'current_version_id'];
            $variants = [];

            if ($photo->wasChanged($contentFields)) {
                $variants[] = 'all';

                Cache::forget("gallery:{$photo->gallery_id}:media_counts");
                Cache::forget("gallery:{$photo->gallery_id}:download_size");
            }

            // Any of these changes what the thumbnails, the URLs or the
            // downloadable bytes look like, so the image list must be rebuilt.
            if ($photo->wasChanged(['name', 'path', 'status', 'archived_at'])) {
                Cache::forget("gallery:{$photo->gallery_id}:photos:ready:image:ids");
            }

            if ($photo->wasChanged('status')) {
                Cache::forget("gallery:{$photo->gallery_id}:first_image");
                Cache::forget("gallery:{$photo->gallery_id}:photos:ready:ids");
                Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
                Cache::forget("gallery:{$photo->gallery_id}:commented:ids");
            }

            if ($photo->wasChanged('favorited_at')) {
                // The tab count and the export list read this cached list, so
                // it must be dropped when a rating of 5 (or 0) changes the
                // heart outside toggleFavorite().
                Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
                $variants[] = 'favorites';
            } elseif ($photo->wasChanged($contentFields) && ($photo->favorited_at || $photo->getOriginal('favorited_at'))) {
                $variants[] = 'favorites';
            }

            if ($photo->wasChanged('rating')) {
                Cache::forget("gallery:{$photo->gallery_id}:rated_count");
            }

            // Archiving changes what clients see and what the ZIP contains, so
            // the live lists, the counts and both archive variants go stale.
            if ($photo->wasChanged('archived_at')) {
                $variants[] = 'all';

                Cache::forget("gallery:{$photo->gallery_id}:photos:ids");
                Cache::forget("gallery:{$photo->gallery_id}:photos:ready:ids");
                Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
                Cache::forget("gallery:{$photo->gallery_id}:commented:ids");
                Cache::forget("gallery:{$photo->gallery_id}:first_image");
                Cache::forget("gallery:{$photo->gallery_id}:media_counts");
                Cache::forget("gallery:{$photo->gallery_id}:download_size");

                if ($photo->favorited_at) {
                    $variants[] = 'favorites';
                }
            }

            // Stored bytes moved: the team's and the gallery's cached totals
            // are wrong now. `current_version_id` also changes which version
            // rows are excluded from the gallery total.
            if ($photo->wasChanged(['size', 'stored_size', 'raw_path', 'disk', 'current_version_id'])) {
                StorageUsageService::forgetForGallery($photo->gallery_id);
            }

            if ($variants !== []) {
                static::invalidateGalleryArchives($photo, array_values(array_unique($variants)));
            }

            // Rating, favourite state, size, version count and upload date all
            // feed the owner grid filters and sorts. Any update can reorder
            // that grid, so advance the query cache stamp once per write.
            static::bumpGalleryCacheVersion((int) $photo->gallery_id);
        });

        static::deleted(function (Photo $photo) {
            static::forgetGalleryCaches((int) $photo->gallery_id);
            StorageUsageService::forgetForGallery($photo->gallery_id);

            $variants = ['all'];

            if ($photo->favorited_at) {
                $variants[] = 'favorites';
            }

            static::invalidateGalleryArchives($photo, $variants);
        });
    }

    /**
     * @param  array<int, string>  $variants
     */
    protected static function invalidateGalleryArchives(Photo $photo, array $variants): void
    {
        if (! $photo->gallery) {
            return;
        }

        try {
            app(GalleryArchiveService::class)->invalidate($photo->gallery, $variants);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function gallery()
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Older versions retained for this logical photo.
     */
    public function versions()
    {
        return $this->hasMany(PhotoVersion::class);
    }

    /**
     * The version row currently represented by this photo, when restoring or
     * otherwise pointing the current photo at a historical version.
     */
    public function currentVersion()
    {
        return $this->belongsTo(PhotoVersion::class, 'current_version_id');
    }

    /**
     * Number of the version currently represented by this photo. The current
     * file has no version row when it is the latest upload, so the stored
     * version count is the fallback.
     */
    public function currentVersionNumber(): int
    {
        $number = $this->currentVersion?->version_number ?? $this->version_count;

        return (int) ($number ?: 1);
    }

    /**
     * Memoised version row count for models that did not eager load it.
     */
    protected ?int $storedVersionCountCache = null;

    /**
     * How many historical rows are stored for this logical photo. The current
     * file is not one of them, so a photo with one edit has one row here.
     *
     * This is not `version_count`: that column is a sequence counter that only
     * grows, so the next upload always gets a fresh `version_number` even
     * after older rows are deleted. Rewinding it would make two rows share a
     * number.
     *
     * The count comes from `versions_count` when the query eager loaded it
     * (`withCount('versions')`, one aggregate for the whole page) and is
     * memoised per model otherwise, so a template that asks three times does
     * not run three queries.
     */
    public function storedVersionCount(): int
    {
        if (isset($this->versions_count)) {
            return (int) $this->versions_count;
        }

        return $this->storedVersionCountCache ??= $this->versions()->count();
    }

    /**
     * Versions a visitor can see for this photo: the current file plus every
     * retained historical row. This is the number the badge shows.
     */
    public function versionTotalCount(): int
    {
        return $this->storedVersionCount() + 1;
    }

    /**
     * Whether there is at least one older version to compare against.
     */
    public function hasStoredVersions(): bool
    {
        return $this->storedVersionCount() > 0;
    }

    /**
     * Copy the current photo state into the version history.
     */
    public function snapshotAsVersion(?int $versionNumber = null): PhotoVersion
    {
        return $this->versions()->create([
            'version_number' => $versionNumber ?? ($this->version_count ?: 1),
            'name' => $this->name,
            'source_name' => $this->source_name ?: $this->name,
            'path' => $this->path,
            'raw_path' => $this->raw_path,
            'disk' => $this->diskOrDefault(),
            'size' => $this->size,
            'stored_size' => $this->stored_size ?: $this->size,
            'stored_hash' => $this->stored_hash,
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at ?? $this->created_at ?? now(),
        ]);
    }

    /**
     * Limit the query to photos that finished processing and may be shown.
     */
    #[Scope]
    protected function ready(Builder $query): void
    {
        $query->whereIn('status', self::READY_STATUSES);
    }

    public function isReady(): bool
    {
        return in_array($this->status, self::READY_STATUSES, true);
    }

    /**
     * Limit the query to displayable image files.
     */
    #[Scope]
    protected function images(Builder $query): void
    {
        $query->where(function (Builder $query) {
            foreach (['jpg', 'jpeg', 'png', 'tiff'] as $extension) {
                $query->orWhere('path', 'like', '%.'.$extension);
            }
        });
    }

    public function scopeFavorited($query)
    {
        return $query->whereNotNull('favorited_at');
    }

    public function scopeUnfavorited($query)
    {
        return $query->whereNull('favorited_at');
    }

    /**
     * Photos the photographer rated (1-5 stars). Ratings are internal to the
     * team and are never exposed to gallery clients.
     */
    public function scopeRated($query)
    {
        return $query->whereNotNull('rating');
    }

    public function scopeUnrated($query)
    {
        return $query->whereNull('rating');
    }

    /**
     * Photos the photographer archived: hidden from clients, from the gallery
     * ZIP and from the public tabs, but still on disk.
     */
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Photos that are still live (not archived).
     */
    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function toggleArchive(): void
    {
        $this->update(['archived_at' => $this->archived_at ? null : Carbon::now()]);
    }

    public function archive(): void
    {
        if (! $this->archived_at) {
            $this->update(['archived_at' => Carbon::now()]);
        }
    }

    public function unarchive(): void
    {
        if ($this->archived_at) {
            $this->update(['archived_at' => null]);
        }
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Toggle the favourite and keep the internal 5-stars-means-favourite
     * equivalence in sync: hearting a photo sets five stars, unhearting clears
     * the rating. Clients only have the heart, so their selection also lands as
     * five stars for the photographer.
     *
     * Ratings 1-4 never reach this method; they leave the heart untouched.
     */
    public function toggleFavorite()
    {
        $favorited = ! $this->isFavorited();

        $this->update([
            'favorited_at' => $favorited ? Carbon::now() : null,
            'rating' => $favorited ? 5 : null,
        ]);

        Cache::forget("gallery:{$this->gallery_id}:favorites:ids");
    }

    /**
     * Toggle the favorite state and report whether this action selected the
     * photo and completed the gallery selection limit.
     *
     * Unselecting never reports the limit so guests are not warned when they
     * remove a photo to choose another one.
     */
    public function toggleFavoriteAndCheckSelectionLimit(): bool
    {
        $selecting = ! $this->isFavorited();

        $this->toggleFavorite();

        return $selecting && $this->gallery->isSelectionLimitReached();
    }

    public function isFavorited()
    {
        return $this->favorited_at !== null;
    }

    /**
     * Set the photographer-only star rating.
     *
     * Five stars also selects the photo as a favourite and 0/null clears both
     * the rating and the heart, matching the culling flow: five stars means
     * "keep", zero means "drop". Ratings 1-4 never touch the favourite state,
     * so a client's heart and a photographer's mid rating stay independent.
     */
    public function setRating(?int $rating): void
    {
        $rating = in_array($rating, [1, 2, 3, 4, 5], true) ? $rating : null;

        $attributes = ['rating' => $rating];

        if ($rating === 5) {
            $attributes['favorited_at'] = $this->favorited_at ?: Carbon::now();
        }

        if ($rating === null) {
            $attributes['favorited_at'] = null;
        }

        $this->update($attributes);
    }

    public function clearRating(): void
    {
        $this->setRating(null);
    }

    public function isRated(): bool
    {
        return $this->rating !== null;
    }

    public function next(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->photoIds(), 1);
    }

    public function previous(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->photoIds(), -1);
    }

    public function nextFavorite(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->favoritePhotoIds(), 1);
    }

    public function previousFavorite(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->favoritePhotoIds(), -1);
    }

    public function nextCommented(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->commentedPhotoIds(), 1);
    }

    public function previousCommented(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->commentedPhotoIds(), -1);
    }

    /**
     * Neighbours inside the archived set, so paging through the Archived tab
     * only visits archived photos.
     */
    public function nextArchived(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->archivedPhotoIds(), 1);
    }

    public function previousArchived(): ?Photo
    {
        return $this->navigationNeighbor($this->gallery->archivedPhotoIds(), -1);
    }

    /**
     * Neighbour inside an arbitrary ordered id list.
     *
     * The owner viewer passes the ids produced by the active filter and sort so
     * arrow keys walk exactly the wall the photographer came from. The shared
     * gallery keeps using the cached default lists.
     *
     * @param  Collection<int, int>  $photoIds
     */
    public function neighborIn(Collection $photoIds, int $offset): ?Photo
    {
        return $this->navigationNeighbor($photoIds, $offset);
    }

    /**
     * Find the neighbor photo offset positions away within the given
     * naturally sorted photo id list.
     *
     * The neighbour is hydrated with its gallery so the viewer can build CDN
     * URLs (which read `keep_original_size`) without an extra query per
     * neighbour. It always belongs to the gallery holding this photo.
     *
     * @param  Collection<int, int>  $photoIds
     */
    protected function navigationNeighbor(Collection $photoIds, int $offset): ?Photo
    {
        $currentIndex = $photoIds->search($this->id);

        if ($currentIndex === false) {
            return null;
        }

        $neighborId = $photoIds->get($currentIndex + $offset);

        if ($neighborId === null) {
            return null;
        }

        $neighbor = static::find($neighborId);

        return $neighbor?->setRelation('gallery', $this->gallery);
    }

    /**
     * Queue deletion for the current files and every retained version.
     */
    public function deleteFromDisk()
    {
        foreach ($this->versions()->get() as $version) {
            $version->deleteFromDisk($this->id);
        }

        if ($this->path) {
            DeleteFromDisk::dispatch($this->path, $this->diskOrDefault(), $this->id);
        }

        if ($this->thumb_path) {
            DeleteFromDisk::dispatch($this->thumb_path, $this->diskOrDefault(), $this->id);
        }

        if ($this->raw_path) {
            DeleteFromDisk::dispatch($this->raw_path, $this->diskOrDefault(), $this->id);
        }

        return $this;
    }

    public function download()
    {
        return Storage::disk($this->diskOrDefault())->download($this->path, $this->downloadFileName());
    }

    public function downloadRaw()
    {
        return Storage::disk($this->diskOrDefault())->download($this->raw_path, $this->rawDownloadFileName());
    }

    /**
     * Stream a PDF inline with a forced content type, so the browser's native
     * PDF reader renders it regardless of the content type the upload placed
     * on the stored object.
     */
    public function inline(): StreamedResponse
    {
        return Storage::disk($this->diskOrDefault())->response($this->path, $this->name, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function url(): Attribute
    {
        return Attribute::get(function () {
            if ($this->isImage()) {
                return PhotoUrlService::make($this)->url();
            }

            if ($this->isPdf()) {
                return null;
            }

            $disk = $this->diskOrDefault();
            $diskConfig = config("filesystems.disks.$disk");
            $baseUrl = $diskConfig['url'] ?? null;

            if (! $baseUrl && isset($diskConfig['origin'], $diskConfig['bucket'])) {
                $baseUrl = rtrim($diskConfig['origin'], '/').'/'.$diskConfig['bucket'];
            }

            if ($baseUrl) {
                return rtrim($baseUrl, '/').'/'.ltrim($this->path, '/');
            }

            return Storage::disk($disk)->url($this->path);
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->thumbnail() : null);
    }

    /**
     * Playable URL for a video, shared by the viewers and the version
     * comparison so both read the exact same origin and cache-busting query.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isVideo() ? PhotoUrlService::make($this)->video() : null);
    }

    protected function largeThumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->largeThumbnail() : null);
    }

    /**
     * Untouched original bytes from the storage/CDN, with no resize params.
     * Used by the Closeup Panel: sharpness and blur can only be judged on the
     * native pixels, never on a 2048px preview that has already been
     * resampled and WebP-compressed by the CDN.
     */
    protected function originalUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->original() : null);
    }

    /**
     * Facts about the file, shown in the floating info panel of the viewer.
     *
     * Everything here is derived from the stored row: no EXIF parsing, no
     * network call. The panel is photographer-only, and rows whose value is
     * null are skipped instead of guessed.
     *
     * `uploaded` is the only date shown: `uploaded_at` and `created_at` hold
     * the same instant for a photo that has never been re-uploaded, so showing
     * both duplicated the same line under two different labels.
     *
     * @return array<string, string|null>
     */
    public function infoFacts(): array
    {
        $resolution = $this->width && $this->height
            ? $this->width.' x '.$this->height.' px'
            : null;

        $ratio = $this->width && $this->height
            ? $this->aspectRatioLabel($this->width, $this->height)
            : null;

        return [
            'name' => $this->name,
            'type' => strtoupper(pathinfo($this->name, PATHINFO_EXTENSION)),
            'size' => $this->formattedSize(),
            'resolution' => $resolution,
            'ratio' => $ratio,
            'uploaded' => $this->uploaded_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Simplified aspect ratio ("3:2", "4:5) reduced by the greatest common
     * divisor), the way photographers describe a crop.
     */
    protected function aspectRatioLabel(int $width, int $height): string
    {
        $divisor = $this->greatestCommonDivisor($width, $height);

        return (int) ($width / $divisor).':'.(int) ($height / $divisor);
    }

    protected function greatestCommonDivisor(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a ?: 1;
    }

    protected function smallThumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->smallThumbnail() : null);
    }

    protected function portraitThumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->portraitThumbnail() : null);
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->coverImage() : null);
    }

    public function diskOrDefault(): string
    {
        return $this->disk ?? 'public';
    }

    public function isOnPublicDisk()
    {
        return $this->diskOrDefault() === 'public';
    }

    public function comments()
    {
        return $this->hasMany(PhotoComment::class);
    }

    /**
     * Determine if the photo is an image (jpg, jpeg, png, tiff)
     */
    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'tiff']);
    }

    /**
     * Determine if the photo is a video (mp4, mkv, avi)
     */
    public function isVideo(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'ogg']);
    }

    /**
     * Determine if the photo is a PDF document
     */
    public function isPdf(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return $ext === 'pdf';
    }

    /**
     * Get the photo size formatted for display
     */
    public function formattedSize(): string
    {
        return $this->formatFileSize($this->size);
    }
}
