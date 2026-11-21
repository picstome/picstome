<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeletePhotosRequest;
use App\Services\ExternalGalleryRevisionService;
use App\Traits\Concerns\FindsAuthorizedGallery;
use App\Traits\Concerns\RespondsWithApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PhotoDeleteController extends Controller
{
    use FindsAuthorizedGallery;
    use RespondsWithApiErrors;

    /**
     * Hard-delete photos confirmed by the client. Photos outside the gallery
     * are reported as `not_found` instead of leaking whether they exist.
     */
    public function __invoke(
        DeletePhotosRequest $request,
        ExternalGalleryRevisionService $revisions,
    ): JsonResponse {
        $gallery = $this->findAuthorizedGallery($request);

        if ($gallery instanceof JsonResponse) {
            return $gallery;
        }

        /** @var Collection<int, int> $requested */
        $requested = collect($request->input('photo_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(500)
            ->values();

        $photos = $gallery->photos()
            ->with('versions')
            ->whereKey($requested)
            ->get()
            ->keyBy('id');

        $deleted = [];
        $notFound = [];

        DB::transaction(function () use ($request, $requested, $photos, &$deleted, &$notFound): void {
            foreach ($requested as $id) {
                $photo = $photos->get($id);

                if (! $photo || ! $request->user()->can('delete', $photo)) {
                    $notFound[] = (string) $id;

                    continue;
                }

                $photo->deleteFromDisk()->delete();
                $deleted[] = (string) $id;
            }
        });

        Log::info('external.photos.delete', [
            'user_id' => $request->user()->id,
            'gallery_id' => (string) $gallery->ulid,
            'photo_ids' => $deleted,
        ]);

        return response()->json([
            'deleted' => $deleted,
            'not_found' => $notFound,
            'rev' => $revisions->for($gallery->fresh()),
        ]);
    }
}
