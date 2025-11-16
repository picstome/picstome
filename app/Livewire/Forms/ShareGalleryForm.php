<?php

namespace App\Livewire\Forms;

use App\Models\Gallery;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ShareGalleryForm extends Form
{
    public Gallery $gallery;

    #[Validate('required|boolean')]
    public $selectable = false;

    #[Validate('required|boolean')]
    public $downloadable = false;

    #[Validate('nullable|required_if:limitedSelection,true|integer')]
    public $selectionLimit = null;

    #[Validate('required|boolean')]
    public $limitedSelection = false;

    #[Validate('required|boolean')]
    public $passwordProtected = false;

    #[Validate('nullable|required_if:passwordProtected,true')]
    public $password = null;

    #[Validate('required|boolean')]
    public $watermarked = false;

    #[Validate('required|boolean')]
    public $descriptionEnabled = false;

    #[Validate('nullable|required_if:descriptionEnabled,true|string|max:1000')]
    public $description = null;

    #[Validate('required|boolean')]
    public $commentsEnabled = true;

    public function setGallery(Gallery $gallery)
    {
        $this->gallery = $gallery;

        $this->selectable = (bool) $gallery->is_share_selectable;
        $this->downloadable = (bool) $gallery->is_share_downloadable;
        $this->selectionLimit = $gallery->share_selection_limit;
        $this->limitedSelection = $gallery->is_share_selectable && $gallery->share_selection_limit;
        $this->passwordProtected = (bool) $gallery->share_password;
        $this->watermarked = (bool) $gallery->is_share_watermarked;
        $this->descriptionEnabled = ! empty($gallery->share_description);
        $this->description = $gallery->share_description;
        $this->commentsEnabled = (bool) $gallery->are_comments_enabled;
    }

    public function update()
    {
        $this->validate();

        $this->gallery->update([
            'is_share_selectable' => $this->selectable,
            'is_share_downloadable' => $this->downloadable,
            'is_share_watermarked' => $this->watermarked,
            'share_selection_limit' => $this->limitedSelection
                ? $this->selectionLimit
                : null,
            'share_password' => $this->passwordProtected && $this->password
                ? Hash::make($this->password)
                : null,
            'share_description' => $this->descriptionEnabled && $this->description
                ? $this->description
                : null,
            'are_comments_enabled' => $this->commentsEnabled,
        ]);
    }
}
