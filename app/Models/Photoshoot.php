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
        $bytes = $this->getTotalStorageSize();

        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = floor(log($bytes, 1024));
        $size = round($bytes / pow(1024, $unitIndex), 2);

        return $size . ' ' . $units[$unitIndex];
    }

    public function getTotalPhotosCount()
    {
        return $this->galleries()
            ->withCount('photos')
            ->get()
            ->sum('photos_count');
    }
}
