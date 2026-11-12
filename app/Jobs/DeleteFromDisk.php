<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a file from storage unless another photo or version still references
 * it. The ignore IDs are used when the owning row is being deleted and may not
 * have been removed from the database yet (for example with the sync queue).
 */
class DeleteFromDisk implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $path,
        public $disk = 'public',
        public ?int $ignorePhotoId = null,
        public ?int $ignoreVersionId = null,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->isStillReferenced()) {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }

    protected function isStillReferenced(): bool
    {
        // Each path column is checked independently instead of through a
        // `where(...)->orWhere(...)` group. MySQL cannot use an index for an
        // `OR` across different columns, so the grouped version scanned the
        // whole table on every check (p95 1.65s). Split queries are ~1ms each
        // because every one of them is a plain index lookup.
        foreach (['path', 'raw_path', 'thumb_path'] as $column) {
            if ($this->referencedIn(Photo::class, $column, $this->ignorePhotoId)) {
                return true;
            }

            if ($this->referencedIn(PhotoVersion::class, $column, $this->ignoreVersionId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string<Photo|PhotoVersion>  $model
     */
    protected function referencedIn(string $model, string $column, ?int $ignoreId): bool
    {
        return $model::query()
            ->where('disk', $this->disk)
            ->where($column, $this->path)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
