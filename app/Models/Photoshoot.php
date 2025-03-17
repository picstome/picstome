<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photoshoot extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoshootFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'date' => 'datetime',
            'price' => 'integer',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function galleries()
    {
        return $this->hasMany(Gallery::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function deleteGalleries()
    {
        $this->galleries()->cursor()->each(
            fn (Gallery $gallery) => $gallery->deletePhotos()->delete()
        );

        return $this;
    }
}
