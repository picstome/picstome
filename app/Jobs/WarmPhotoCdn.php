<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Services\PhotoUrlService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches the grid variants through the CDN before publishing a photo.
 *
 * A successful request proves the processed object is reachable and leaves the
 * resized variant cached at the edge. If warming fails after all retries the
 * photo is published anyway: hiding it forever is worse than a cold first hit.
 *
 * Warming is therefore best-effort per URL. A single aborted transfer (the CDN
 * answering 500 with "Transfer aborted") used to throw out of the loop, leaving
 * the photo in `warming` and burning the queue retries for work that `failed()`
 * would publish anyway.
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
            $this->warm($url);
        }

        $this->markProcessed($media);
    }

    /**
     * Warm a single URL, retrying transient CDN failures with a short backoff.
     *
     * Never throws: warming exists to save the first visitor a cold cache miss,
     * so a failed variant must not keep the photo out of the gallery.
     */
    protected function warm(string $url): void
    {
        try {
            Http::timeout(max(1, (int) config('picstome.cdn_warming.timeout', 15)))
                ->withHeaders(['Accept' => 'image/webp,image/*,*/*'])
                ->retry(
                    times: $this->tries,
                    sleepMilliseconds: fn (int $attempt): int => $attempt * 250,
                    when: fn (Throwable $exception): bool => $this->isTransient($exception),
                    throw: false,
                )
                ->get($url)
                ->throw();
        } catch (Throwable $exception) {
            $transient = $this->isTransient($exception);

            $context = [
                'photo_id' => $this->photoId,
                'version_id' => $this->versionId,
                'url' => $url,
                'status' => $exception instanceof RequestException
                    ? $exception->response?->status()
                    : null,
                'error' => $exception->getMessage(),
            ];

            if ($transient) {
                Log::warning('CDN warming failed for one variant; continuing.', $context);
            } else {
                Log::error('CDN warming failed with a non-retryable error; continuing.', $context);
            }
        }
    }

    /**
     * A 5xx or a transport failure is worth retrying; a 4xx means the object or
     * the URL is wrong and retrying only delays the inevitable.
     */
    protected function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response?->serverError();
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
