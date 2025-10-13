<?php

namespace App\Jobs;

use App\Models\Photo;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public $timeout = 60 * 4; // 4 minutes

    public $temporaryDirectory;

    public $temporaryPhotoPath;

    /**
     * Create a new job instance.
     */
    public function __construct(public Photo $photo)
    {
        $this->temporaryDirectory = TemporaryDirectory::make();

        $this->temporaryPhotoPath = $this->temporaryDirectory->path($this->photo->name);

        $readStream = Storage::disk('public')->readStream($this->photo->path);
        $writeStream = fopen($this->temporaryPhotoPath, 'w');

        if (! $readStream) {
            throw new Exception("Failed to open read stream for: {$this->photo->path}");
        }
        if ($writeStream === false) {
            throw new Exception("Failed to open write stream for: {$this->temporaryPhotoPath}");
        }

        stream_copy_to_stream($readStream, $writeStream);

        fclose($readStream);
        fclose($writeStream);
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
