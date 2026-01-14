<?php

namespace App\Models;

use App\Traits\FormatsFileSize;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

class Moodboard extends Model
{
    use FormatsFileSize, HasFactory;

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

        static::deleted(function (Moodboard $moodboard) {
            Cache::forget("moodboard:{$moodboard->id}:first_image");
            Cache::forget("moodboard:{$moodboard->id}:photos_count");
            Cache::forget("moodboard:{$moodboard->id}:photos");
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function coverPhoto()
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    public function addPhoto(UploadedFile $photo)
    {
        $team = $this->team;

        $photoSize = $photo->getSize();

        if ($team->storage_limit !== null && ! $team->canStoreFile($photoSize)) {
            throw new \Exception('Not enough storage');
        }

        $photoModel = $this->photos()->create([
            'name' => $photo->getClientOriginalName(),
            'size' => $photoSize,
            'path' => FileUploadConfiguration::isUsingS3()
                ? tap($this->storage_path.'/'.$photo->getFilename(), function ($path) use ($photo) {
                    Storage::disk('s3')->move($photo->getRealPath(), $path);
                })
                : $photo->store(path: $this->storage_path, options: ['disk' => 's3']),
            'disk' => 's3',
            'status' => 'pending',
        ]);

        return $photoModel;
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "{$this->team->storage_path}/moodboards/{$this->ulid}/photos";
        });
    }

    public function deletePhotos()
    {
        $this->photos()->cursor()->each(
            fn (Photo $photo) => $photo->deleteFromDisk()->delete()
        );

        return $this;
    }

    public function getTotalStorageSize()
    {
        return $this->photos()->sum('size');
    }

    public function getFormattedStorageSize()
    {
        return $this->formatFileSize($this->getTotalStorageSize());
    }

    public function setCoverPhoto(Photo $photo)
    {
        if (! $this->is($photo->moodboard)) {
            throw new \Exception('Photo does not belong to this moodboard');
        }

        $this->update(['cover_photo_id' => $photo->id]);
    }

    public function removeCoverPhoto()
    {
        $this->update(['cover_photo_id' => null]);
        $this->setRelation('coverPhoto', null);
    }

    public function firstImage()
    {
        return Cache::remember("moodboard:{$this->id}:first_image", now()->addHours(24), function () {
            $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();

            return $photos->first(fn ($photo) => $photo->isImage());
        });
    }

    public function photosCount()
    {
        return Cache::remember("moodboard:{$this->id}:photos_count", now()->addHours(24), function () {
            return $this->photos()->count();
        });
    }

    #[Attribute]
    protected function slug(): Attribute
    {
        return Attribute::get(fn () => Str::slug($this->title));
    }
}
