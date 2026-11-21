<?php

namespace App\Traits\Concerns;

use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resolves the gallery of an external API request and authorises access.
 */
trait FindsAuthorizedGallery
{
    protected function findAuthorizedGallery(Request $request, string $key = 'gallery_id'): Gallery|JsonResponse
    {
        $gallery = Gallery::query()
            ->where('ulid', (string) $request->string($key))
            ->first();

        if (! $gallery) {
            return $this->apiError('unknown_gallery', __('The gallery does not exist.'), 404);
        }

        if (! $request->user()->can('view', $gallery)) {
            return $this->apiError('not_member', __('You do not have access to this gallery.'), 403);
        }

        return $gallery;
    }
}
