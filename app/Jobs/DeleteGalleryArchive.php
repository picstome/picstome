<?php

namespace App\Jobs;

use App\Models\GalleryArchive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes an archive object after its row was invalidated or removed.
 *
 * The path and disk are passed explicitly so the job still cleans up storage
 * when the database row has already been deleted.
 */
class DeleteGalleryArchive implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public int $archiveId,
        public string $path,
        public string $disk,
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk($this->disk);

        try {
            if ($disk->exists($this->path)) {
                $disk->delete($this->path);
            }
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }

        GalleryArchive::query()
            ->whereKey($this->archiveId)
            ->where('path', $this->path)
            ->update([
                'path' => null,
                'storage_deleted_at' => now(),
            ]);
    }
}
