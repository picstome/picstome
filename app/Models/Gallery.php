<?php

namespace App\Models;

use App\Traits\FormatsFileSize;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

class Gallery extends Model
{
    /** @use HasFactory<\Database\Factories\GalleryFactory> */
    use FormatsFileSize, HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'is_shared' => 'boolean',
            'is_share_selectable' => 'boolean',
            'is_share_downloadable' => 'boolean',
            'is_share_watermarked' => 'boolean',
            'keep_original_size' => 'boolean',
            'share_selection_limit' => 'integer',
        ];
    }

    public static function booted()
    {
        static::creating(function (Gallery $gallery) {
            if (empty($gallery->ulid)) {
                $gallery->ulid = Str::ulid();
            }
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

    public function favorites()
    {
        return $this->photos()->favorited();
    }

    public function download($favorites = false)
    {
        $zipName = Str::of($this->name)->slug()->append('.zip');

        $headers = [
            'Content-Disposition' => "attachment; filename=\"{$zipName}\"",
            'Content-Type' => 'application/octet-stream',
        ];

        $photos = $favorites ? $this->favorites : $this->photos;

        return new StreamedResponse(fn () => $this->getPhotosZipStream($photos, $zipName), 200, $headers);
    }

    protected function getPhotosZipStream($photos, $zipName)
    {
        $zip = new ZipStream(outputName: $zipName);

        $photos->each(function ($photo) use ($zip) {
            $stream = Storage::disk('public')->readStream($photo->path);

            $zip->addFileFromStream($photo->name, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $zip->finish();

        return $zip;
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
            'path' => $photo->store(
                path: $this->storage_path,
                options: ['disk' => 'public']
            ),
        ]);

        $team->increment('storage_used', $photoSize);

        return $photoModel;
    }

    protected function storagePath(): Attribute
    {
        return Attribute::get(function () {
            return "{$this->team->storage_path}/galleries/{$this->ulid}/photos";
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
}
