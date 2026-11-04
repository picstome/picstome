<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

class ManifestRequest extends ApiFormRequest
{
    /**
     * `since` is accepted for forward compatibility but v1 always returns the
     * full photo list.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'gallery' => ['required', 'string', 'ulid'],
            'since' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
