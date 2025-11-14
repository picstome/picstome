<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoComment extends Model
{
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
