<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Services\PhotoUrlService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches the grid variants through the CDN before publishing a photo.
 *
 * A successful request proves the processed object is reachable and leaves the
 * resized variant cached at the edge. If warming fails after all retries the
 * photo is published anyway: hiding it forever is worse than a cold first hit.
 */
class WarmPhotoCdn implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries;

    /** @var list<int> */
    public array $backoff = [2, 10, 30];

    public function __construct(
        public int $photoId,
        public ?int $versionId = null,
    ) {
        $this->tries = max(1, (int) config('picstome.cdn_warming.tries', 3));
    }

    public function handle(): void
    {
        $media = $this->media();

        if (! $media || $media->status !== Photo::STATUS_WARMING || ! $media->isImage()) {
            return;
        }

        $urls = array_values(array_unique(array_filter([
            PhotoUrlService::make($media)->smallThumbnail(),
            PhotoUrlService::make($media)->thumbnail(),
        ])));

        foreach ($urls as $url) {
            Http::timeout(max(1, (int) config('picstome.cdn_warming.timeout', 15)))
                ->withHeaders(['Accept' => 'image/webp,image/*,*/*'])
                ->get($url)
                ->throw();
        }

        $this->markProcessed($media);
    }

    public function failed(?Throwable $exception): void
    {
        $media = $this->media();

        if ($media && $media->status === Photo::STATUS_WARMING) {
            Log::warning('CDN warming failed; publishing the photo anyway.', [
                'photo_id' => $this->photoId,
                'version_id' => $this->versionId,
                'error' => $exception?->getMessage(),
            ]);

            $this->markProcessed($media);
        }

        if ($exception) {
            report($exception);
        }
    }

    protected function media(): Photo|PhotoVersion|null
    {
        return $this->versionId
            ? PhotoVersion::query()->find($this->versionId)
            : Photo::query()->find($this->photoId);
    }

    protected function markProcessed(Photo|PhotoVersion $media): void
    {
        $media->update(['status' => Photo::STATUS_PROCESSED]);

        if ($media instanceof Photo && $media->current_version_id) {
            $media->versions()
                ->whereKey($media->current_version_id)
                ->update(['status' => Photo::STATUS_PROCESSED]);
        }
    }
}
