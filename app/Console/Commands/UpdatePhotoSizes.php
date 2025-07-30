<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpdatePhotoSizes extends Command
{
    protected $signature = 'photos:update-sizes {--dry-run : Run without making changes}';

    protected $description = 'Update photo sizes for galleries where keep_original_size is false';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        $galleries = Gallery::where('keep_original_size', false)
            ->with('photos')
            ->get();

        if ($galleries->isEmpty()) {
            $this->info('No galleries found with keep_original_size set to false.');

            return;
        }

        $totalPhotos = $galleries->sum(fn ($gallery) => $gallery->photos->count());
        $this->info("Found {$galleries->count()} galleries with {$totalPhotos} photos to process.");

        $updated = 0;
        $skipped = 0;

        $allPhotos = $galleries->flatMap->photos;
        $progressBar = $this->output->createProgressBar($allPhotos->count());

        foreach ($allPhotos as $photo) {
            $progressBar->advance();

            if (! Storage::disk('public')->exists($photo->path)) {
                $skipped++;

                continue;
            }

            try {
                $actualSize = Storage::disk('public')->size($photo->path);

                if ($photo->size !== $actualSize) {
                    if (! $isDryRun) {
                        $photo->update(['size' => $actualSize]);
                    }
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing {$photo->name}: ".$e->getMessage());
                $skipped++;
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Summary:');
        $this->line("  Photos checked: {$totalPhotos}");
        $this->line("  Photos updated: {$updated}");
        $this->line("  Photos skipped: {$skipped}");

        if ($isDryRun && $updated > 0) {
            $this->info('Run without --dry-run to apply the changes.');
        } elseif ($updated > 0) {
            $this->info('Photo sizes updated successfully!');
        } else {
            $this->info('All photo sizes are already correct.');
        }
    }
}
