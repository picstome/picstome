<?php

namespace App\Http\Controllers;

use App\Exceptions\GalleryUploadException;
use App\Http\Requests\CheckGalleryUploadsRequest;
use App\Http\Requests\CompleteGalleryUploadRequest;
use App\Http\Requests\GalleryUploadStatusRequest;
use App\Http\Requests\SignGalleryUploadRequest;
use App\Http\Requests\StoreGalleryUploadRequest;
use App\Models\Gallery;
use App\Services\GalleryUploadService;
use App\Support\PendingDirectUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * HTTP entry point for the gallery uploader.
 *
 * Uploads run outside the gallery Livewire component so they survive an
 * in-app navigation: each file is a regular authenticated request that moves
 * the file to storage and creates the photo as soon as it arrives.
 *
 * When Livewire's temporary upload disk is S3 (production), the browser PUTs
 * each file straight to the bucket with a pre-signed URL (`sign()`) and then
 * registers it (`complete()`), exactly like the Livewire uploader did. The
 * bytes never pass through PHP, so `post_max_size` and the proxy timeout do
 * not limit file size. Without S3 the file is POSTed to `store()`.
 */
class GalleryUploadController extends Controller
{
    /**
     * Conflict and quota context for a batch of selected file names.
     */
    public function check(
        CheckGalleryUploadsRequest $request,
        Gallery $gallery,
        GalleryUploadService $uploads,
    ): JsonResponse {
        return response()->json([
            'duplicates' => $uploads->duplicateNames($gallery, $request->input('names', [])),
            'remaining_storage_bytes' => $uploads->remainingStorageBytes($gallery),
            'extensions' => config('picstome.upload_extensions'),
            'file_versions_enabled' => (bool) config('picstome.file_versions_enabled'),
        ]);
    }

    /**
     * Store one uploaded file and return its photo id and processing status.
     */
    public function store(
        StoreGalleryUploadRequest $request,
        Gallery $gallery,
        GalleryUploadService $uploads,
    ): JsonResponse {
        set_time_limit(300);

        return $this->storeFile(
            $gallery,
            $request->file('file'),
            (string) $request->input('action', GalleryUploadService::ACTION_VERSION),
            $uploads,
        );
    }

    /**
     * Pre-signed S3 PUT URL for one file, after the checks that only need its
     * name and size. Answers `direct: false` when uploads are not on S3 so the
     * uploader falls back to `store()`.
     */
    public function sign(
        SignGalleryUploadRequest $request,
        Gallery $gallery,
        GalleryUploadService $uploads,
        GenerateSignedUploadUrl $signer,
    ): JsonResponse {
        if (! FileUploadConfiguration::isUsingS3()) {
            return response()->json(['direct' => false]);
        }

        $name = (string) $request->input('name');

        try {
            $uploads->assertCanUpload(
                $gallery,
                $name,
                (int) $request->input('size'),
                (string) $request->input('action', GalleryUploadService::ACTION_VERSION),
            );
        } catch (GalleryUploadException $exception) {
            return $this->uploadError($exception);
        }

        $signed = $signer->forS3(new PendingDirectUpload($name));

        // The browser sets Host itself and refuses to override it.
        $headers = collect($signed['headers'])
            ->except(['Host', 'host'])
            ->map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value)
            ->all();

        return response()->json([
            'direct' => true,
            'path' => $signed['path'],
            'url' => $signed['url'],
            'headers' => (object) $headers,
        ]);
    }

    /**
     * Store a file the browser already uploaded to the S3 temporary area.
     */
    public function complete(
        CompleteGalleryUploadRequest $request,
        Gallery $gallery,
        GalleryUploadService $uploads,
    ): JsonResponse {
        set_time_limit(300);

        // Only paths signed by `sign()` (HMAC with the app key) are accepted,
        // so a client cannot register somebody else's temporary object.
        $path = TemporaryUploadedFile::extractPathFromSignedPath((string) $request->input('path'));

        if ($path === false || ! FileUploadConfiguration::isUsingS3()) {
            return response()->json(['message' => __('The file could not be uploaded. Please try again.')], 422);
        }

        $file = TemporaryUploadedFile::createFromLivewire($path);

        if (! $file->exists()) {
            return response()->json(['message' => __('The file could not be uploaded. Please try again.')], 422);
        }

        $response = $this->storeFile(
            $gallery,
            $file,
            (string) $request->input('action', GalleryUploadService::ACTION_VERSION),
            $uploads,
        );

        // On S3 a stored file was already moved out of the temporary area;
        // anything left (a rejected or skipped file) is deleted now instead of
        // waiting for the tmp cleanup. Deleting a missing object is a no-op.
        $file->delete();

        return $response;
    }

    protected function storeFile(
        Gallery $gallery,
        UploadedFile $file,
        string $action,
        GalleryUploadService $uploads,
    ): JsonResponse {
        try {
            $photo = $uploads->store($gallery, $file, $action);
        } catch (GalleryUploadException $exception) {
            return $this->uploadError($exception);
        } catch (\RuntimeException $exception) {
            if (preg_match('/S3|storage/i', $exception->getMessage()) === 1) {
                report($exception);

                return response()->json([
                    'message' => __('The photo could not be stored. Please try again.'),
                    'code' => 'storage_error',
                ], 503);
            }

            throw $exception;
        }

        if ($photo === null) {
            return response()->json(['skipped' => true]);
        }

        return response()->json([
            'photo_id' => $photo->id,
            'status' => $photo->status,
            'remaining_storage_bytes' => $uploads->remainingStorageBytes($gallery),
        ]);
    }

    protected function uploadError(GalleryUploadException $exception): JsonResponse
    {
        $status = in_array($exception->reason, [
            GalleryUploadException::STORAGE_QUOTA,
            GalleryUploadException::FILE_TOO_LARGE,
        ], true) ? 413 : 422;

        return response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->reason,
        ], $status);
    }

    /**
     * Statuses used by the uploader to drop finalized rows from the panel.
     */
    public function statuses(
        GalleryUploadStatusRequest $request,
        Gallery $gallery,
    ): JsonResponse {
        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['statuses' => (object) []]);
        }

        $statuses = $gallery->photos()
            ->whereKey($ids->all())
            ->pluck('status', 'id');

        return response()->json(['statuses' => (object) $statuses->all()]);
    }
}
