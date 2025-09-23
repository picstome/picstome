<?php

namespace App\Models;

use App\Traits\FormatsFileSize;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photoshoot extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoshootFactory> */
    use FormatsFileSize, HasFactory;

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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function deleteGalleries()
    {
        $this->galleries()->cursor()->each(
            fn (Gallery $gallery) => $gallery->deletePhotos()->delete()
        );

        return $this;
    }

    public function getTotalStorageSize()
    {
        return $this->galleries()
            ->with('photos')
            ->get()
            ->sum(function ($gallery) {
                return $gallery->photos->sum('size');
            });
    }

    public function getFormattedStorageSize()
    {
        return $this->formatFileSize($this->getTotalStorageSize());
    }

    public function getTotalPhotosCount()
    {
        return $this->galleries()
            ->withCount('photos')
            ->get()
            ->sum('photos_count');
    }
}
