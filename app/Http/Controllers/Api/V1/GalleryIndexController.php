<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GalleryIndexRequest;
use App\Http\Resources\Api\V1\GalleryResource;
use Illuminate\Http\JsonResponse;

class GalleryIndexController extends Controller
{
    /**
     * List the galleries of the token owner's current team. Clients use the
     * optional `search` filter to find a gallery before adding it locally.
     */
    public function __invoke(GalleryIndexRequest $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));
        $perPage = (int) $request->input('per_page', 25);

        $galleries = $request->user()
            ->currentTeam
            ->galleries()
            ->with(['coverPhoto', 'firstImagePhoto'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->only(['search', 'per_page']));

        return GalleryResource::collection($galleries)->response();
    }
}
