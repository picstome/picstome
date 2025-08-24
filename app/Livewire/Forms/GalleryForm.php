<?php

namespace App\Livewire\Forms;

use App\Models\Gallery;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class GalleryForm extends Form
{
    public ?Photoshoot $photoshoot = null;

    public ?Gallery $gallery;

    public ?string $name = null;

    public $expirationDate = null;

    public $keepOriginalSize = false;

    public ?int $photoshoot_id = null;

    protected function rules()
    {
        return [
            'photoshoot_id' => [
                'nullable',
                Rule::exists('photoshoots', 'id')->where(fn($query) => $query->where('team_id', auth()->user()->currentTeam->id)),
            ],
            'name' => ['nullable', 'string'],
            'expirationDate' => ['nullable', 'date', 'after_or_equal:today'],
            'keepOriginalSize' => ['required', 'boolean'],
        ];
    }

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;
    }

    public function setGallery(Gallery $gallery)
    {
        $this->gallery = $gallery;

        $this->name = $gallery->name;
        $this->expirationDate = $gallery->expiration_date?->format('Y-m-d');
        $this->photoshoot_id = $gallery->photoshoot_id;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->galleries()->create([
            'photoshoot_id' => $this->photoshoot?->id,
            'name' => $this->name ?? __('Untitled'),
            'keep_original_size' => $this->keepOriginalSize,
            'expiration_date' => $this->expirationDate ?: null,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->gallery->update([
            'photoshoot_id' => $this->photoshoot_id ?: null,
            'name' => $this->name ?? __('Untitled'),
            'expiration_date' => $this->expirationDate ?: null,
        ]);
    }
}
