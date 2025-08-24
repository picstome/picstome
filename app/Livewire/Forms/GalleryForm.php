<?php

namespace App\Livewire\Forms;

use App\Models\Gallery;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class GalleryForm extends Form
{
    public ?Photoshoot $photoshoot = null;

    public ?Gallery $gallery;

    public ?string $name = null;

    public $expirationDate = null;

    #[Validate('required|boolean')]
    public $keepOriginalSize = false;

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;
    }

    public function setGallery(Gallery $gallery)
    {
        $this->gallery = $gallery;

        $this->name = $gallery->name;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->galleries()->create([
            'photoshoot_id' => $this->photoshoot?->id,
            'name' => $this->name ?? __('Untitled'),
            'keep_original_size' => $this->keepOriginalSize,
            'expiration_date' => $this->expirationDate,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->gallery->update([
            'name' => $this->name ?? __('Untitled'),
        ]);
    }
}
