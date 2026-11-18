<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks the server which of the selected files already exist in the gallery,
 * before any bytes are uploaded.
 */
class CheckGalleryUploadsRequest extends FormRequest
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
            'names' => ['required', 'array', 'max:500'],
            'names.*' => ['string', 'max:255'],
        ];
    }
}
