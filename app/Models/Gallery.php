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
use Illuminate\Support\Facades\Log;

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

        set_time_limit(1200); // Set max execution time to 20 minutes

        return new StreamedResponse(function () use ($photos, $zipName) {
            try {
                $this->getPhotosZipStream($photos, $zipName);
            } catch (\Throwable $e) {
                Log::error('Fatal error streaming gallery zip', [
                    'gallery_id' => $this->id,
                    'zip_name' => $zipName,
                    'exception' => $e->getMessage(),
                ]);
                // Optionally, output a message or just fail silently
            }
        }, 200, $headers);
    }

    protected function getPhotosZipStream($photos, $zipName)
    {
        $zip = new ZipStream(outputName: $zipName);

        $photos->each(function ($photo) use ($zip) {
            try {
                $stream = Storage::disk($photo->disk)->readStream($photo->path);
                if ($stream === false) {
                    Log::error('Failed to open photo stream for zip', [
                        'photo_id' => $photo->id,
                        'photo_name' => $photo->name,
                        'photo_path' => $photo->path,
                        'disk' => $photo->disk,
                    ]);
                    return; // Skip this photo
                }
                $zip->addFileFromStream($photo->name, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            } catch (\Throwable $e) {
                Log::error('Exception adding photo to zip', [
                    'photo_id' => $photo->id,
                    'photo_name' => $photo->name,
                    'photo_path' => $photo->path,
                    'disk' => $photo->disk,
                    'exception' => $e->getMessage(),
                ]);
                // Optionally skip this photo and continue
            }
        });

        try {
            $zip->finish();
        } catch (\Throwable $e) {
            Log::error('Exception finishing zip stream', [
                'zip_name' => $zipName,
                'exception' => $e->getMessage(),
            ]);
        }

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
