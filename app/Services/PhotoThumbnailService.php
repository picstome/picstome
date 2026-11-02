<?php

namespace App\Services;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Generates the small WebP used by gallery grids.
 *
 * Thumbnails are stored next to the processed image at a deterministic path,
 * so they can be regenerated, counted in storage usage and deleted with their
 * owning photo without a separate mapping table.
 */
class PhotoThumbnailService
{
    public function pathFor(string $sourcePath): string
    {
        return $sourcePath.'.thumb.webp';
    }

    /**
     * Generate a thumbnail from a local file and publish it next to the
     * stable source path. Returns the stored path or null on failure.
     */
    public function generateFromLocalFile(string $localPath, string $sourcePath, string $disk = 's3'): ?string
    {
        if (! is_file($localPath)) {
            return null;
        }

        $size = max(64, (int) config('picstome.photo_small_thumb_resize', 500));
        $temporaryPath = $localPath.'_thumb.webp';

        try {
            Image::load($localPath)
                ->fit(Fit::Max, $size, $size)
                ->format('webp')
                ->quality(82)
                ->save($temporaryPath);
        } catch (\Throwable $exception) {
            report($exception);
            @unlink($temporaryPath);

            return null;
        }

        if (! is_file($temporaryPath)) {
            return null;
        }

        $targetPath = $this->pathFor($sourcePath);

        $stored = Storage::disk($disk)->putFileAs(
            path: dirname($targetPath),
            file: new File($temporaryPath),
            name: basename($targetPath),
        );

        @unlink($temporaryPath);

        return $stored ?: null;
    }

    /**
     * Download an already-stored image, generate its thumbnail and upload it.
     */
    public function generateForStoredPath(string $sourcePath, string $disk = 's3'): ?string
    {
        if (! Storage::disk($disk)->exists($sourcePath)) {
            return null;
        }

        $directory = 'photo-processing-temp';
        $relativePath = $directory.'/thumb-'.Str::ulid().'.tmp';

        Storage::disk('local')->makeDirectory($directory);

        $localPath = Storage::disk('local')->path($relativePath);
        $source = Storage::disk($disk)->readStream($sourcePath);

        if (! is_resource($source)) {
            return null;
        }

        $destination = fopen($localPath, 'w');

        if ($destination === false) {
            fclose($source);

            return null;
        }

        stream_copy_to_stream($source, $destination);

        fclose($source);
        fclose($destination);

        $stored = $this->generateFromLocalFile($localPath, $sourcePath, $disk);

        @unlink($localPath);

        return $stored;
    }
}
