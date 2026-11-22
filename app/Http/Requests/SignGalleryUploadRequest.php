<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks for a pre-signed S3 URL so the browser can upload one file straight to
 * the bucket. Name and size rules are applied by `GalleryUploadService`.
 */
class SignGalleryUploadRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'in:version,replace,skip'],
        ];
    }
}
