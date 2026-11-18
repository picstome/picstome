<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single background upload from the gallery uploader.
 *
 * The file size is intentionally not bounded with Laravel's `max:` rule: the
 * gallery accepts files up to 5 GB (PHP's upload_max_filesize), while the
 * external API has a lower limit. `GalleryUploadService` enforces the real
 * limit so both entry points share it.
 */
class StoreGalleryUploadRequest extends FormRequest
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
        $extensions = implode(',', config('picstome.upload_extensions'));

        return [
            'file' => ['required', 'file', 'extensions:'.$extensions],
            'action' => ['nullable', 'string', 'in:version,replace,skip'],
        ];
    }
}
