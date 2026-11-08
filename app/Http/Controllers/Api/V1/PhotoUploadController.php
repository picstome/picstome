<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PhotoAdded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadPhotoRequest;
use App\Models\Gallery;
use App\Models\Photo as GalleryPhoto;
use App\Services\ExternalGalleryRevisionService;
use App\Traits\Concerns\RespondsWithApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Throwable;

class PhotoUploadController extends Controller
{
    use RespondsWithApiErrors;

    public function __invoke(
        UploadPhotoRequest $request,
        ExternalGalleryRevisionService $revisions,
    ): JsonResponse {
        $gallery = Gallery::query()
            ->where('ulid', (string) $request->string('gallery_id'))
            ->first();

        if (! $gallery) {
            return $this->apiError('unknown_gallery', __('The gallery does not exist.'), 404);
        }

        if (! $request->user()->can('view', $gallery)) {
            return $this->apiError('not_member', __('You do not have access to this gallery.'), 403);
        }

        $file = $request->file('file');
        $filename = $this->safeFilename((string) $request->string('filename'), $file);

        $uploadedSize = (int) $file->getSize();

        if ($uploadedSize <= 0) {
            return $this->apiError('invalid_image', __('The uploaded file is empty.'), 422);
        }

        // Hash and dimensions are read from the temporary upload before the
        // file is moved to storage. If the client sent its own hash, verify it
        // here so a truncated or mutating upload is rejected before it creates
        // a photo/version that ProcessPhoto cannot read.
        $hash = hash_file('sha256', $file->getRealPath());
        $expectedHash = strtolower((string) $request->string('hash'));
        $expectedSize = (int) $request->integer('size');

        if ($expectedSize > 0 && $uploadedSize !== $expectedSize) {
            return $this->apiError(
                'upload_integrity_error',
                __('The uploaded file size does not match the client copy. Please try again.'),
                422,
            );
        }

        if ($expectedHash !== '' && ! hash_equals($expectedHash, $hash)) {
            return $this->apiError(
                'upload_integrity_error',
                __('The uploaded file changed during upload. Please try again.'),
                422,
            );
        }

        [$width, $height] = $this->dimensions($file);

        if ($this->isRasterImage($filename) && ($width === null || $height === null)) {
            return $this->apiError('invalid_image', __('The uploaded file is not a readable image.'), 422);
        }

        $upload = new UploadedFile(
            $file->getRealPath(),
            $filename,
            $file->getMimeType(),
            null,
            true,
        );

        $created = false;
        $unchanged = false;

        try {
            $photo = $gallery->withPhotoIdentityLock(Gallery::identityKey($filename), function () use ($gallery, $upload, $hash, $filename, &$created, &$unchanged): GalleryPhoto {
                $existing = $gallery->findLogicalPhotoBySourceName($filename);

                if ($existing) {
                    if (is_string($existing->hash) && hash_equals($existing->hash, $hash)) {
                        $unchanged = true;

                        return $existing;
                    }

                    $created = true;

                    return $gallery->addVersion($upload, $existing);
                }

                $created = true;

                return $gallery->addPhoto($upload);
            });
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Not enough storage') {
                return $this->apiError('storage_quota_exceeded', __('There is not enough storage available.'), 413);
            }

            if ($exception instanceof \RuntimeException
                && preg_match('/S3|storage/i', $exception->getMessage()) === 1) {
                report($exception);

                return $this->apiError(
                    'storage_error',
                    __('The photo could not be stored. Please try again.'),
                    503,
                );
            }

            throw $exception;
        }

        if (! $unchanged) {
            $photo->forceFill([
                'hash' => $hash,
                'hash_algo' => 'sha256',
                'width' => $width,
                'height' => $height,
            ])->saveQuietly();

            PhotoAdded::dispatch($photo);
        }

        return response()->json([
            'id' => (string) $photo->id,
            'filename' => $photo->name,
            'rev' => $revisions->for($gallery->fresh()),
            'hash' => $hash,
            'size' => $uploadedSize,
            'version_count' => $photo->version_count,
            'version_number' => $photo->currentVersionNumber(),
        ], $created ? 201 : 200);
    }

    /**
     * Normalize the client filename while keeping the extension intact.
     */
    protected function safeFilename(string $filename, UploadedFile $file): string
    {
        $filename = Gallery::sanitizeFileName(basename($filename));

        if ($filename === '' || $filename === '.') {
            return Gallery::sanitizeFileName($file->getClientOriginalName());
        }

        return $filename;
    }

    protected function isRasterImage(string $filename): bool
    {
        return in_array(
            strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            ['jpg', 'jpeg', 'png', 'tiff', 'tif'],
            true,
        );
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function dimensions(UploadedFile $file): array
    {
        $size = @getimagesize($file->getRealPath());

        if (! is_array($size)) {
            return [null, null];
        }

        return [(int) $size[0], (int) $size[1]];
    }
}
