<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Photo;
use App\Services\PhotoDownloadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Photo
 */
class PhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $url = app(PhotoDownloadService::class)->temporaryUrl(
            $this->resource,
            'processed',
            (int) config('external_api.url_ttl_minutes', 15),
        );

        return [
            'id' => (string) $this->id,
            'filename' => $this->name,
            'size' => (int) $this->size,
            'hash' => $this->hash,
            'download_hash' => $this->stored_hash,
            'hash_algo' => 'sha256',
            'url' => $url ?? $this->url,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'width' => $this->width,
            'height' => $this->height,
            'identity_key' => $this->identity_key,
            'version_count' => $this->version_count,
            'version_number' => $this->currentVersionNumber(),
            'favorited_at' => $this->favorited_at?->toIso8601String(),
            'is_favorite' => $this->isFavorited(),
            // Photographer-only 1-5 star rating. Clients never see it; the
            // companion app uses it to merge hearts with the stars it asked for.
            'rating' => $this->rating,
            // Archived photos are still returned (the companion app must be
            // able to show and unarchive them); this field is the only signal.
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
