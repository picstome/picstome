<?php

namespace App\Models;

use App\Traits\FormatsFileSize;
use Database\Factories\GalleryArchiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A temporary ZIP snapshot of a gallery.
 *
 * Archives are created on demand, stored in object storage and served through
 * a short-lived presigned URL. They are invalidated when the gallery content
 * changes; the row is kept as history while the stored object is deleted.
 */
class GalleryArchive extends Model
{
    /** @use HasFactory<GalleryArchiveFactory> */
    use FormatsFileSize, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'photos_total' => 'integer',
            'photos_processed' => 'integer',
            'photos_skipped' => 'integer',
            'images_count' => 'integer',
            'videos_count' => 'integer',
            'pdfs_count' => 'integer',
            'downloads_count' => 'integer',
            'last_downloaded_at' => 'datetime',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'storage_deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GalleryArchive $archive): void {
            if (empty($archive->ulid)) {
                $archive->ulid = (string) Str::ulid();
            }
        });
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isBuilding(): bool
    {
        return $this->status === 'building';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->path !== null && ! $this->isExpired();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'building', 'ready'], true)
            && ! $this->isExpired();
    }

    public function recordDownload(): void
    {
        $this->increment('downloads_count', 1, ['last_downloaded_at' => now()]);
    }

    public function formattedSize(): string
    {
        return $this->formatFileSize((int) $this->size_bytes);
    }

    public function isFavorites(): bool
    {
        return $this->variant === 'favorites';
    }
}
