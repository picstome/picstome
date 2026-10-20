<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use App\Services\PhotoUrlService;
use App\Traits\FormatsFileSize;
use Database\Factories\PhotoVersionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Historical representation of a logical gallery photo.
 *
 * The active file lives on photos; this table stores superseded files and the
 * metadata needed for comparison, downloads and restoration.
 */
class PhotoVersion extends Model
{
    /** @use HasFactory<PhotoVersionFactory> */
    use FormatsFileSize, HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
            'stored_size' => 'integer',
            'version_number' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function diskOrDefault(): string
    {
        return $this->disk ?: config('picstome.disk', 's3');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->url() : null);
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->thumbnail() : null);
    }

    protected function largeThumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->smallThumbnail() : null);
    }

    protected function smallThumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => $this->isImage() ? PhotoUrlService::make($this)->largeThumbnail() : null);
    }

    /**
     * Queue deletion of this version's files. The owning photo ID can be
     * ignored while a photo and its versions are being deleted together.
     */
    public function deleteFromDisk(?int $ignorePhotoId = null)
    {
        if ($this->path) {
            DeleteFromDisk::dispatch($this->path, $this->diskOrDefault(), $ignorePhotoId, $this->id);
        }

        if ($this->raw_path) {
            DeleteFromDisk::dispatch($this->raw_path, $this->diskOrDefault(), $ignorePhotoId, $this->id);
        }

        if ($this->thumb_path) {
            DeleteFromDisk::dispatch($this->thumb_path, $this->diskOrDefault(), $ignorePhotoId, $this->id);
        }

        return $this;
    }

    /**
     * Delete files synchronously for an explicit version deletion, while
     * preserving any path still referenced by another row.
     */
    public function deleteFilesImmediately()
    {
        foreach (array_filter([$this->path, $this->raw_path, $this->thumb_path]) as $path) {
            if ($this->isReferencedElsewhere($path)) {
                continue;
            }

            Storage::disk($this->diskOrDefault())->delete($path);
        }

        return $this;
    }

    protected function isReferencedElsewhere(string $path): bool
    {
        $matchesPath = fn ($query) => $query
            ->where('path', $path)
            ->orWhere('raw_path', $path)
            ->orWhere('thumb_path', $path);

        return Photo::query()
            ->where('disk', $this->diskOrDefault())
            ->where($matchesPath)
            ->exists()
            || static::query()
                ->whereKeyNot($this->getKey())
                ->where('disk', $this->diskOrDefault())
                ->where($matchesPath)
                ->exists();
    }

    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'tiff'], true);
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
}
