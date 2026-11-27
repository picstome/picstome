<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            'photoshoot_id' => $this->photoshoot_id,
            'expiration_date' => $this->expirationDate(),
            'is_public' => (bool) $this->is_public,
            'cover_url' => $cover?->small_thumbnail_url,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The date cast is a Carbon at runtime; parsing also accepts the raw
     * string Larastan infers from the column.
     */
    private function expirationDate(): ?string
    {
        return $this->expiration_date === null
            ? null
            : Carbon::parse($this->expiration_date)->toDateString();
    }
}
