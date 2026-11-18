<?php

namespace App\Http\Controllers;

use App\Exceptions\GalleryUploadException;
use App\Http\Requests\CheckGalleryUploadsRequest;
use App\Http\Requests\GalleryUploadStatusRequest;
use App\Http\Requests\StoreGalleryUploadRequest;
use App\Models\Gallery;
use App\Services\GalleryUploadService;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for the gallery uploader.
 *
 * Uploads run outside the gallery Livewire component so they survive an
 * in-app navigation: each file is a regular authenticated request that moves
 * the file to storage and creates the photo as soon as it arrives.
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

        try {
            $photo = $uploads->store(
                $gallery,
                $request->file('file'),
                (string) $request->input('action', GalleryUploadService::ACTION_VERSION),
            );
        } catch (GalleryUploadException $exception) {
            $status = in_array($exception->reason, [
                GalleryUploadException::STORAGE_QUOTA,
                GalleryUploadException::FILE_TOO_LARGE,
            ], true) ? 413 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->reason,
            ], $status);
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
