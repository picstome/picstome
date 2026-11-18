<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Polls the processing status of photos created by the uploader.
 */
class GalleryUploadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('gallery')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['present', 'array', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
