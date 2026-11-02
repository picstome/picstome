<?php

namespace App\Models;

use Database\Factories\PhotoCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PhotoComment extends Model
{
    /** @use HasFactory<PhotoCommentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'annotated_version_number' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::created(function ($comment) {
            $comment->load('photo');
            Cache::forget("gallery:{$comment->photo->gallery_id}:commented:ids");
            Cache::forget("gallery:{$comment->photo->gallery_id}:comments_count");
        });

        static::deleted(function ($comment) {
            $comment->load('photo');
            Cache::forget("gallery:{$comment->photo->gallery_id}:commented:ids");
            Cache::forget("gallery:{$comment->photo->gallery_id}:comments_count");
        });
    }

    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this comment is pinned to a position on the photo.
     */
    public function hasCoordinates(): bool
    {
        return $this->x !== null && $this->y !== null;
    }

    /**
     * Whether the annotation was made on a version other than the current one.
     * Plain comments (without an annotated version) are never stale.
     */
    public function isFromOtherVersion(int $currentVersionNumber): bool
    {
        return $this->annotated_version_number !== null
            && (int) $this->annotated_version_number !== $currentVersionNumber;
    }
}
