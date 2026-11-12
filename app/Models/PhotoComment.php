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
            'time_seconds' => 'float',
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
     * Whether this comment points at a moment of the video.
     *
     * Temporal comments use the same `annotated_version_number` bookkeeping as
     * image pins, so a comment made on an older cut is flagged the same way.
     */
    public function hasTimestamp(): bool
    {
        return $this->time_seconds !== null;
    }

    /**
     * Whether the comment is anchored to the media (a pin or a second), as
     * opposed to a plain message.
     */
    public function isAnchored(): bool
    {
        return $this->hasCoordinates() || $this->hasTimestamp();
    }

    /**
     * Playhead position rendered as `mm:ss` (or `h:mm:ss` for long videos).
     */
    public function timecode(): ?string
    {
        if (! $this->hasTimestamp()) {
            return null;
        }

        return self::formatTimecode($this->time_seconds);
    }

    /**
     * Render a number of seconds as `mm:ss`, the label used by the comment
     * chip, the player rail and the composer.
     */
    public static function formatTimecode(float $seconds): string
    {
        $total = max(0, (int) floor($seconds));
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $remaining = $total % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remaining);
        }

        return sprintf('%02d:%02d', $minutes, $remaining);
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
