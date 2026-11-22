<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registers a file the browser already uploaded to S3. `path` is the signed
 * temporary path returned by the sign endpoint.
 */
class CompleteGalleryUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('gallery')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:1024'],
            'action' => ['nullable', 'string', 'in:version,replace,skip'],
        ];
    }
}
