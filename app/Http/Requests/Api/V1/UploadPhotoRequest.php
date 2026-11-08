<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

class UploadPhotoRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $extensions = implode(',', config('picstome.upload_extensions'));

        return [
            'gallery_id' => ['required', 'string', 'ulid'],
            'filename' => ['required', 'string', 'max:255'],
            'hash' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'size' => ['nullable', 'integer', 'min:1'],
            'file' => [
                'required',
                'file',
                'max:'.(int) config('external_api.max_upload_kb'),
                'extensions:'.$extensions,
            ],
        ];
    }
}
