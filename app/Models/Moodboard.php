<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Moodboard extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function photoshoot(): BelongsTo
    {
        return $this->belongsTo(Photoshoot::class);
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class)
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    public function addPhoto(Photo $photo, ?int $sortOrder = null)
    {
        $sortOrder = $sortOrder ?? $this->photos()->max('sort_order') + 1;

        $this->photos()->attach($photo, ['sort_order' => $sortOrder]);
    }

    public function removePhoto(Photo $photo)
    {
        $this->photos()->detach($photo->id);
    }

    public function reorderPhotos(array $photoIds)
    {
        foreach ($photoIds as $index => $photoId) {
            $this->photos()->updateExistingPivot($photoId, ['sort_order' => $index]);
        }
    }
}
