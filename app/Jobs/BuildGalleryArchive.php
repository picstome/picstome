<?php

namespace App\Jobs;

use App\Models\GalleryArchive;
use App\Services\GalleryArchiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds a gallery ZIP in the background.
 *
 * The job is unique per archive row through WithoutOverlapping. The service
 * re-checks the archive status while building so an invalidation cancels the
 * work before it uploads a stale ZIP.
 */
class BuildGalleryArchive implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(public int $archiveId)
    {
        $this->timeout = (int) config('picstome.gallery_archives.timeout', 3600);
        $this->onQueue((string) config('picstome.gallery_archives.queue', 'default'));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping('gallery-archive-'.$this->archiveId, 300, $this->timeout + 300)];
    }

    public function handle(GalleryArchiveService $service): void
    {
        $archive = GalleryArchive::find($this->archiveId);

        if (! $archive || in_array($archive->status, ['expired', 'ready'], true)) {
            return;
        }

        $service->build($archive);
    }

    public function failed(Throwable $exception): void
    {
        $archive = GalleryArchive::find($this->archiveId);

        if (! $archive || $archive->status === 'expired' || $archive->isReady()) {
            return;
        }

        GalleryArchive::query()
            ->whereKey($archive->id)
            ->whereIn('status', ['pending', 'building', 'failed'])
            ->update([
                'status' => 'failed',
                'failure_reason' => Str::limit($exception->getMessage(), 500),
                'completed_at' => now(),
            ]);

        if ($archive->path) {
            DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: config('picstome.disk', 's3'));
        }
    }
}
