<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves individual photo, RAW and version downloads.
 *
 * When the storage disk supports temporary URLs (S3), the request is
 * redirected to a presigned URL so storage serves the file directly and can
 * handle range/resume requests. Other disks keep the streamed fallback.
 */
class PhotoDownloadService
{
    public function download(Photo|PhotoVersion $model, string $type = 'processed'): StreamedResponse|RedirectResponse
    {
        $url = $this->temporaryUrl($model, $type);

        if ($url !== null) {
            return redirect()->away($url);
        }

        return $type === 'raw'
            ? $model->downloadRaw()
            : $model->download();
    }

    public function temporaryUrl(Photo|PhotoVersion $model, string $type = 'processed'): ?string
    {
        $path = $this->path($model, $type);

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk($model->diskOrDefault());

        if (! $disk->providesTemporaryUrls()) {
            return null;
        }

        $filename = $this->filename($model, $type);

        return $disk->temporaryUrl($path, now()->addMinutes($this->ttlMinutes($model)), [
            'ResponseContentType' => 'application/octet-stream',
            'ResponseContentDisposition' => $this->contentDisposition($filename),
        ]);
    }

    public function path(Photo|PhotoVersion $model, string $type = 'processed'): ?string
    {
        return $type === 'raw' ? $model->raw_path : $model->path;
    }

    public function filename(Photo|PhotoVersion $model, string $type = 'processed'): string
    {
        return $type === 'raw'
            ? $model->rawDownloadFileName()
            : $model->downloadFileName();
    }

    /**
     * Videos get a longer TTL because they are large and may need to be
     * resumed after a pause. S3 checks the expiry when the request starts.
     */
    public function ttlMinutes(Photo|PhotoVersion $model): int
    {
        $minutes = $model->isVideo()
            ? config('picstome.video_download_url_ttl_minutes', 360)
            : config('picstome.photo_download_url_ttl_minutes', 10);

        return max(1, (int) $minutes);
    }

    protected function contentDisposition(string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($filename)) ?: 'download';

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            $fallback,
        );
    }
}
