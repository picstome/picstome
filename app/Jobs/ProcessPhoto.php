<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
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
    public function __construct(public Photo $photo) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! $this->shouldProcess()) {
            $this->photo->update(['status' => 'skipped']);

            return;
        }

        $this->prepareTemporaryPhoto();

        if ($this->deleteIfOversizedAndOriginal()) {
            return;
        }

        $this->resizePhoto();

        @unlink($this->temporaryPhotoPath);
    }

    protected function shouldProcess(): bool
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'tiff'];
        $extension = strtolower(pathinfo($this->photo->path, PATHINFO_EXTENSION));

        return in_array($extension, $allowedExtensions, true);
    }

    protected function prepareTemporaryPhoto()
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
            'status' => 'processed',
        ]);

        if ($previousPath) {
            Storage::disk('s3')->delete($previousPath);
        }
    }

    protected function deleteIfOversizedAndOriginal(): bool
    {
        if (! $this->photo->gallery->keep_original_size) {
            return false;
        }

        $maxPixels = config('picstome.max_photo_pixels');
        [$width, $height] = getimagesize($this->temporaryPhotoPath);

        if ($width * $height <= $maxPixels) {
            return false;
        }

        $this->photo->deleteFromDisk()->delete();

        @unlink($this->temporaryPhotoPath);

        return true;
    }
}
