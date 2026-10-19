<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RecalculateStoredSizesCommand extends Command
{
    protected $signature = 'storage:recalculate {--dry-run : Report changes without saving them}';

    protected $description = 'Recalculate stored bytes for photos and their versions from the storage disk.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $checked = 0;

        Photo::query()->chunkById(100, function ($photos) use ($dryRun, &$updated, &$checked): void {
            foreach ($photos as $photo) {
                $checked++;
                $storedSize = $this->storedSize($photo->path, $photo->raw_path, $photo->thumb_path, $photo->diskOrDefault());

                if ($storedSize !== null && $photo->stored_size !== $storedSize) {
                    $updated++;

                    if (! $dryRun) {
                        $photo->updateQuietly(['stored_size' => $storedSize]);
                    }
                }
            }
        });

        PhotoVersion::query()->chunkById(100, function ($versions) use ($dryRun, &$updated, &$checked): void {
            foreach ($versions as $version) {
                $checked++;
                $storedSize = $this->storedSize($version->path, $version->raw_path, $version->thumb_path, $version->diskOrDefault());

                if ($storedSize !== null && $version->stored_size !== $storedSize) {
                    $updated++;

                    if (! $dryRun) {
                        $version->updateQuietly(['stored_size' => $storedSize]);
                    }
                }
            }
        });

        $this->info("Checked {$checked} records; updated {$updated}.".($dryRun ? ' Dry run only.' : ''));

        return self::SUCCESS;
    }

    protected function storedSize(?string $path, ?string $rawPath, ?string $thumbPath, string $disk): ?int
    {
        $total = 0;

        try {
            foreach (array_filter([$path, $rawPath, $thumbPath]) as $file) {
                if (Storage::disk($disk)->exists($file)) {
                    $total += (int) Storage::disk($disk)->size($file);
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Could not read a stored file: '.$e->getMessage());

            return null;
        }

        return $total;
    }
}
