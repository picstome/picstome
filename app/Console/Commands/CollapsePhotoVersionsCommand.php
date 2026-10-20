<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;

/**
 * Emergency rollback helper for removing version history before dropping the
 * feature. It is a dry run unless --force is supplied, and only deletes
 * historical files when --delete-files is also explicit.
 */
class CollapsePhotoVersionsCommand extends Command
{
    protected $signature = 'photos:collapse-versions {--force : Apply the changes} {--delete-files : Also delete historical files from storage}';

    protected $description = 'Remove historical photo versions while keeping the current version.';

    public function handle(): int
    {
        $apply = (bool) $this->option('force');
        $deleteFiles = (bool) $this->option('delete-files');
        $photos = 0;
        $versions = 0;

        Photo::query()
            ->has('versions')
            ->with('versions')
            ->chunkById(100, function ($photoBatch) use ($apply, $deleteFiles, &$photos, &$versions): void {
                foreach ($photoBatch as $photo) {
                    $photos++;

                    foreach ($photo->versions as $version) {
                        $versions++;

                        if (! $apply) {
                            continue;
                        }

                        if ($deleteFiles) {
                            $version->deleteFilesImmediately();
                        }

                        $version->delete();
                    }

                    if ($apply) {
                        $photo->updateQuietly([
                            'version_count' => 1,
                            'current_version_id' => null,
                        ]);
                    }
                }
            });

        $mode = $apply ? 'Applied' : 'Dry run';
        $this->info("{$mode}: {$photos} photos, {$versions} historical versions.");

        if (! $apply) {
            $this->line('Run with --force to remove historical version rows.');
        }

        if ($apply && ! $deleteFiles) {
            $this->warn('Historical files were kept in storage because --delete-files was not provided.');
        }

        return self::SUCCESS;
    }
}
