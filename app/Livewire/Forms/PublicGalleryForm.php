<?php

namespace App\Livewire\Forms;

use App\Models\Gallery;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PublicGalleryForm extends Form
{
    public Gallery $gallery;

    #[Validate('required|boolean')]
    public $isPublic = false;

    public function setGallery(Gallery $gallery)
    {
        $this->gallery = $gallery;

        $this->isPublic = (bool) $gallery->is_public;
    }

    public function update()
    {
        $this->validate();

        $this->gallery->update([
            'is_public' => $this->isPublic,
        ]);
    }
}