<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GalleryStoreRequest;
use App\Http\Resources\Api\V1\GalleryResource;
use Illuminate\Http\JsonResponse;

class GalleryStoreController extends Controller
{
    /**
     * Create a private gallery in the token owner's current team.
     *
     * The gallery is empty: clients add photos through POST /photos. When the
     * client omits `expiration_date`, the gallery expires one month from now;
     * an explicit null keeps it forever on plans with unlimited retention.
     */
    public function __invoke(GalleryStoreRequest $request): JsonResponse
    {
        if ($response = $request->expirationNotAllowedResponse()) {
            return $response;
        }

        $user = $request->user();
        $validated = $request->validated();

        $gallery = $user->currentTeam->galleries()->create([
            'photoshoot_id' => $validated['photoshoot_id'] ?? null,
            'name' => $this->galleryName($validated['name'] ?? null),
            'keep_original_size' => (bool) ($validated['keep_original_size']
                ?? $user->galleryDefaults()['keep_original_size']),
            'expiration_date' => $request->expirationDate(),
        ]);

        $gallery->load(['coverPhoto', 'firstImagePhoto']);

        return (new GalleryResource($gallery))
            ->response()
            ->setStatusCode(201);
    }

    private function galleryName(?string $name): string
    {
        $name = trim((string) $name);

        return $name !== '' ? $name : __('Untitled');
    }
}
