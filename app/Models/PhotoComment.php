<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoComment extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoCommentFactory> */
    use HasFactory;

    protected $guarded = [];

    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
