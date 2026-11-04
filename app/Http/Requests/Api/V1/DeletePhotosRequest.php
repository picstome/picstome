<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

class DeletePhotosRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'gallery_id' => ['required', 'string', 'ulid'],
            'photo_ids' => ['required', 'array', 'min:1', 'max:500'],
            'photo_ids.*' => ['integer', 'distinct'],
        ];
    }
}
