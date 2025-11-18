<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Image;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public $timeout = 60 * 4; // 4 minutes

    public $temporaryPhotoPath;

    public $temporaryPhotoRelativePath;

    /**
     * Create a new job instance.
     */
    public function __construct(public Photo $photo)
    {
        $tempDir = 'photo-processing-temp';
        $ulid = Str::ulid()->toBase32();
        $tempFileName = $ulid.'_'.$this->photo->name;
        $tempFileRelativePath = $tempDir.'/'.$tempFileName;

        Storage::disk('local')->makeDirectory($tempDir);

        $this->temporaryPhotoPath = Storage::disk('local')->path($tempFileRelativePath);

        file_put_contents(
            $this->temporaryPhotoPath,
            Storage::disk('s3')->get($this->photo->path)
        );

        $this->temporaryPhotoRelativePath = $tempFileRelativePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->resizePhoto();

        $this->generateThumbnail();

        if ($this->photo->getOriginal('disk') === null || $this->photo->getOriginal('disk') === 'public') {
            Storage::disk('public')->delete($this->photo->getOriginal('path'));
        }

        $this->triggerWsrvCache($this->photo);

        @unlink($this->temporaryPhotoPath);
    }

    /**
     * Fire-and-forget request to wsrv.nl to trigger caching
     */
    protected function triggerWsrvCache(Photo $photo): void
    {
        if (app()->environment('production')) {
            Http::async()->get($photo->url);
            Http::async()->get($photo->thumbnail_url);
            Http::async()->get($photo->large_thumbnail_url);
            Http::async()->get($photo->small_thumbnail_url);
        }
    }

    protected function resizePhoto()
    {
        $previousPath = $this->photo->path;

        if (! $this->photo->gallery->keep_original_size) {
            Image::load($this->temporaryPhotoPath)
                ->width(config('picstome.photo_resize'))
                ->height(config('picstome.photo_resize'))
                ->save();
        }

        $fileSize = filesize($this->temporaryPhotoPath);

        $newPath = Storage::disk('s3')->putFile(
            path: $this->photo->gallery->storage_path,
            file: new File($this->temporaryPhotoPath),
        );

        $this->photo->update([
            'path' => $newPath,
            'size' => $fileSize,
            'disk' => 's3',
        ]);

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }
    }

    protected function generateThumbnail()
    {
        Image::load($this->temporaryPhotoPath)
            ->width(config('picstome.photo_thumb_resize'))
            ->height(config('picstome.photo_thumb_resize'))
            ->save();

        $newThumbPath = Storage::disk('s3')->putFile(
            path: $this->photo->gallery->storage_path,
            file: new File($this->temporaryPhotoPath),
            options: 'public',
        );

        $this->photo->update([
            'thumb_path' => $newThumbPath,
            'disk' => 's3',
        ]);
    }
}
