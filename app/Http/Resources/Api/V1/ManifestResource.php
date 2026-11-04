<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Gallery;
use App\Services\ExternalGalleryRevisionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Gallery
 */
class ManifestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photos = $this->photos->sortBy('name', SORT_NATURAL)->values();

        return [
            'gallery_id' => (string) $this->ulid,
            'name' => $this->name,
            'web_url' => route('galleries.show', ['gallery' => $this->id]),
            'rev' => app(ExternalGalleryRevisionService::class)->for($this->resource),
            'photos' => PhotoResource::collection($photos)->resolve(),
        ];
    }
}
