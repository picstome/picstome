<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Moodboard extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'title' => 'string',
            'description' => 'string',
        ];
    }

    public static function booted()
    {
        static::creating(function (Moodboard $moodboard) {
            if (empty($moodboard->ulid)) {
                $moodboard->ulid = Str::ulid();
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
