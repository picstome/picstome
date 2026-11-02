<?php

namespace App\Console\Commands;

use App\Jobs\DeleteFromDisk;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Services\PhotoThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Backfills the small WebP thumbnails used by gallery grids.
 *
 * The command is idempotent: existing thumbnails are skipped unless --force is
 * passed. Use --dry-run first on large accounts because every generated
 * thumbnail is downloaded, encoded and uploaded.
 */
class GeneratePhotoThumbnailsCommand extends Command
{
    protected $signature = 'photos:generate-thumbnails
                            {--dry-run : Report how many thumbnails are missing without writing anything}
                            {--force : Regenerate thumbnails that already exist}';

    protected $description = 'Generate missing WebP gallery thumbnails for photos and versions.';

    public function handle(PhotoThumbnailService $thumbnails): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        if ($dryRun) {
            $missing = $this->missingCount(Photo::query(), $force)
                + $this->missingCount(PhotoVersion::query(), $force);

            $this->info("{$missing} thumbnails would be generated. Dry run only.");

            return self::SUCCESS;
        }

        Photo::query()->chunkById(100, function ($photos) use ($thumbnails, $force, &$generated, &$skipped, &$failed): void {
            foreach ($photos as $photo) {
                $this->generateFor($photo, $thumbnails, $force, $generated, $skipped, $failed);
            }
        });

        PhotoVersion::query()->chunkById(100, function ($versions) use ($thumbnails, $force, &$generated, &$skipped, &$failed): void {
            foreach ($versions as $version) {
                $this->generateFor($version, $thumbnails, $force, $generated, $skipped, $failed);
            }
        });

        $this->info("Generated {$generated} thumbnails; skipped {$skipped}; failed {$failed}.");

        return self::SUCCESS;
    }

    protected function missingCount($query, bool $force): int
    {
        return $query
            ->where(function ($query) {
                foreach (['jpg', 'jpeg', 'png', 'tiff'] as $extension) {
                    $query->orWhere('path', 'like', '%.'.$extension);
                }
            })
            ->when(! $force, fn ($query) => $query->where(function ($query) {
                $query->whereNull('thumb_path')->orWhere('thumb_path', '');
            }))
            ->count();
    }

    protected function generateFor(
        Model $media,
        PhotoThumbnailService $thumbnails,
        bool $force,
        int &$generated,
        int &$skipped,
        int &$failed,
    ): void {
        if (! $this->isImage($media)) {
            $skipped++;

            return;
        }

        $disk = $media->diskOrDefault();
        $previousThumbPath = $media->thumb_path;

        if (! $force && $previousThumbPath && Storage::disk($disk)->exists($previousThumbPath)) {
            $skipped++;

            return;
        }

        $newThumbPath = $thumbnails->generateForStoredPath($media->path, $disk);

        if (! $newThumbPath) {
            $failed++;
            $this->warn("Could not generate thumbnail for {$media->path}.");

            return;
        }

        $media->updateQuietly([
            'thumb_path' => $newThumbPath,
            'stored_size' => $this->storedSize($media, $disk, $newThumbPath),
        ]);

        if ($previousThumbPath && $previousThumbPath !== $newThumbPath) {
            DeleteFromDisk::dispatch($previousThumbPath, $disk);
        }

        $generated++;
    }

    protected function isImage(Model $media): bool
    {
        $extension = strtolower(pathinfo((string) $media->path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'tiff'], true);
    }

    protected function storedSize(Model $media, string $disk, string $newThumbPath): int
    {
        $total = 0;

        foreach (array_filter([$media->path, $media->raw_path, $newThumbPath]) as $path) {
            if (Storage::disk($disk)->exists($path)) {
                $total += (int) Storage::disk($disk)->size($path);
            }
        }

        return $total;
    }
}
