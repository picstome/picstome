<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ManifestRequest;
use App\Http\Resources\Api\V1\ManifestResource;
use App\Traits\Concerns\FindsAuthorizedGallery;
use App\Traits\Concerns\RespondsWithApiErrors;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    use FindsAuthorizedGallery;
    use RespondsWithApiErrors;

    /**
     * Return the full photo manifest for a gallery. v1 ignores `since` and
     * always sends the whole list; the client treats `rev` as an opaque cursor.
     */
    public function __invoke(ManifestRequest $request): JsonResponse
    {
        $gallery = $this->findAuthorizedGallery($request, 'gallery');

        if ($gallery instanceof JsonResponse) {
            return $gallery;
        }

        $gallery->load('photos');

        return response()->json((new ManifestResource($gallery))->resolve());
    }
}
