<?php

namespace App\Models;

use App\Traits\FormatsFileSize;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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
            'is_shared' => 'boolean',
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

    public function photos()
    {
        return $this->hasMany(MoodboardPhoto::class);
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
        ]);

        return $photoModel;
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "moodboards/{$this->ulid}/photos";
        });
    }

    public function deletePhotos()
    {
        $this->photos()->cursor()->each(
            fn (MoodboardPhoto $photo) => $photo->deleteFromDisk()->delete()
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

    #[Attribute]
    protected function slug(): Attribute
    {
        return Attribute::get(fn () => Str::slug($this->title));
    }
}
