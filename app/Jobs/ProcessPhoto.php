<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

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

        $this->temporaryDirectory->delete();
    }

    protected function resizePhoto()
    {
        if ($this->photo->gallery->keep_original_size) {
            return;
        }

        Image::load($this->temporaryPhotoPath)
            ->width(config('picstome.photo_resize'))
            ->height(config('picstome.photo_resize'))
            ->save();

        $previousPath = $this->photo->path;
        
        $this->photo->update([
            'path' => Storage::disk('public')->putFile(
                path: $this->photo->gallery->storage_path,
                file: new File($this->temporaryPhotoPath),
            ),
            'size' => filesize($this->temporaryPhotoPath),
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

        $this->photo->update([
            'thumb_path' => Storage::disk('public')->putFile(
                path: $this->photo->gallery->storage_path,
                file: new File($this->temporaryPhotoPath),
            ),
        ]);
    }
}
