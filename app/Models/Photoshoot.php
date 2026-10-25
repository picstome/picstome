<?php

namespace App\Models;

use App\Traits\FormatsFileSize;
use Database\Factories\PhotoshootFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Photoshoot extends Model
{
    /** @use HasFactory<PhotoshootFactory> */
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

    /**
     * Newest gallery of the photoshoot that contains at least one image.
     * Older galleries are used as fallback when the newest ones are empty.
     */
    public function latestGalleryWithImages(): HasOne
    {
        return $this->hasOne(Gallery::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->whereHas(
                'photos',
                fn (Builder $query) => $query->images(),
            ),
        );
    }

    /**
     * Every photo across the galleries of the photoshoot, used for counters.
     */
    public function photos(): HasManyThrough
    {
        return $this->hasManyThrough(Photo::class, Gallery::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
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

    protected function formattedDate(): Attribute
    {
        return Attribute::get(function () {
            return $this->date?->format('Y-m-d');
        });
    }
}
