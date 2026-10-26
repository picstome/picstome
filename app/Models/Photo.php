<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use App\Services\PhotoUrlService;
use App\Traits\FormatsFileSize;
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

class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use FormatsFileSize, HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'stored_size' => 'integer',
            'version_count' => 'integer',
            'uploaded_at' => 'datetime',
            'favorited_at' => 'datetime',
            'status' => 'string',
        ];
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

        static::created(function ($photo) {
            Cache::forget("gallery:{$photo->gallery_id}:first_image");
            Cache::forget("gallery:{$photo->gallery_id}:photos_count");
            Cache::forget("gallery:{$photo->gallery_id}:photos:ids");
            Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
            Cache::forget("gallery:{$photo->gallery_id}:commented:ids");
        });

        static::deleted(function ($photo) {
            Cache::forget("gallery:{$photo->gallery_id}:first_image");
            Cache::forget("gallery:{$photo->gallery_id}:photos_count");
            Cache::forget("gallery:{$photo->gallery_id}:photos:ids");
            Cache::forget("gallery:{$photo->gallery_id}:favorites:ids");
            Cache::forget("gallery:{$photo->gallery_id}:commented:ids");
        });
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
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at ?? $this->created_at ?? now(),
        ]);
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

    public function toggleFavorite()
    {
        $this->update(['favorited_at' => $this->favorited_at ? null : Carbon::now()]);

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
     * Find the neighbor photo offset positions away within the given
     * naturally sorted photo id list.
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

        return $neighborId === null ? null : static::find($neighborId);
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
        $filename = $this->name;

        if ($this->raw_path) {
            $pathInfo = pathinfo($this->name);
            $filename = $pathInfo['filename'].'.jpg';
        }

        return Storage::disk($this->diskOrDefault())->download($this->path, $filename);
    }

    public function downloadRaw()
    {
        return Storage::disk($this->diskOrDefault())->download($this->raw_path, $this->name);
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
            $baseUrl = ($diskConfig['origin'] ?? null).'/'.($diskConfig['bucket'] ?? null);

            if ($baseUrl) {
                $baseUrl = rtrim($baseUrl, '/');
                $path = ltrim($this->path, '/');

                return $baseUrl.'/'.$path;
            }

            return Storage::disk($disk)->url($this->path);
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->thumbnail() : null);
    }

    protected function largeThumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->largeThumbnail() : null);
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
