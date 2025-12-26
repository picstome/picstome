<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PhotoComment extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoCommentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::created(function ($comment) {
            Cache::forget("gallery:{$comment->photo->gallery_id}:commented");
            Cache::forget("gallery:{$comment->photo->gallery_id}:commented:nav");
        });

        static::deleted(function ($comment) {
            Cache::forget("gallery:{$comment->photo->gallery_id}:commented");
            Cache::forget("gallery:{$comment->photo->gallery_id}:commented:nav");
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
}
