<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Gallery
 */
class GalleryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->coverPhoto ?? $this->firstImagePhoto;

        return [
            'id' => (string) $this->ulid,
            'name' => $this->name,
            'cover_url' => $cover?->small_thumbnail_url,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
