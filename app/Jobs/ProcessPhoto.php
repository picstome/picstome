<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Support\Facades\Http;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public $timeout = 120;

    public $temporaryDirectory;

    public $temporaryPhotoPath;

    /**
     * Create a new job instance.
     */
    public function __construct(public Photo $photo)
    {
        $this->temporaryDirectory = TemporaryDirectory::make();

        $this->temporaryPhotoPath = tap($this->temporaryDirectory->path($this->photo->name), function ($temporaryPhotoPath) {
            file_put_contents(
                $temporaryPhotoPath,
                Storage::disk('public')->get($this->photo->path)
            );
        });
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

        $this->temporaryDirectory->delete();
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

        $newPath = Storage::disk('s3')->putFile(
            path: $this->photo->gallery->storage_path,
            file: new File($this->temporaryPhotoPath),
        );

        $this->photo->update([
            'path' => $newPath,
            'size' => filesize($this->temporaryPhotoPath),
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
