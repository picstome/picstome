<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ManifestRequest;
use App\Http\Resources\Api\V1\ManifestResource;
use App\Models\Gallery;
use App\Traits\Concerns\RespondsWithApiErrors;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    use RespondsWithApiErrors;

    /**
     * Return the full photo manifest for a gallery. v1 ignores `since` and
     * always sends the whole list; the client treats `rev` as an opaque cursor.
     */
    public function __invoke(ManifestRequest $request): JsonResponse
    {
        $gallery = Gallery::query()
            ->where('ulid', (string) $request->string('gallery'))
            ->first();

        if (! $gallery) {
            return $this->apiError('unknown_gallery', __('The gallery does not exist.'), 404);
        }

        if (! $request->user()->can('view', $gallery)) {
            return $this->apiError('not_member', __('You do not have access to this gallery.'), 403);
        }

        $gallery->load('photos');

        return response()->json((new ManifestResource($gallery))->resolve());
    }
}
