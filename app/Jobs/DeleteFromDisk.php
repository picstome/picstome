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
        $matchesPath = fn ($query) => $query
            ->where('path', $this->path)
            ->orWhere('raw_path', $this->path)
            ->orWhere('thumb_path', $this->path);

        return Photo::query()
            ->where('disk', $this->disk)
            ->when($this->ignorePhotoId, fn ($query) => $query->where('id', '!=', $this->ignorePhotoId))
            ->where($matchesPath)
            ->exists()
            || PhotoVersion::query()
                ->where('disk', $this->disk)
                ->when($this->ignoreVersionId, fn ($query) => $query->where('id', '!=', $this->ignoreVersionId))
                ->where($matchesPath)
                ->exists();
    }
}
