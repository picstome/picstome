<?php

namespace App\Livewire\Forms;

use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * Stores the share modal state and writes the chosen options back as the
 * user's default for future galleries. Passwords and descriptions are never
 * persisted as defaults.
 */
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
    public $versionsVisible = false;

    #[Validate('required|boolean')]
    public $descriptionEnabled = false;

    #[Validate('nullable|required_if:descriptionEnabled,true|string|max:1000')]
    public $description = null;

    #[Validate('required|boolean')]
    public $commentsEnabled = true;

    /**
     * Seed the form from the gallery when it is already shared, otherwise from
     * the authenticated user's remembered defaults.
     */
    public function setGallery(Gallery $gallery)
    {
        $this->gallery = $gallery;
        $this->password = null;

        if ($gallery->is_shared) {
            $this->selectable = (bool) $gallery->is_share_selectable;
            $this->downloadable = (bool) $gallery->is_share_downloadable;
            $this->selectionLimit = $gallery->share_selection_limit;
            $this->limitedSelection = $gallery->is_share_selectable && $gallery->share_selection_limit;
            $this->passwordProtected = (bool) $gallery->share_password;
            $this->watermarked = (bool) $gallery->is_share_watermarked;
            $this->versionsVisible = config('picstome.file_versions_enabled')
                && (bool) $gallery->is_share_versions_visible;
            $this->descriptionEnabled = ! empty($gallery->share_description);
            $this->description = $gallery->share_description;
            $this->commentsEnabled = (bool) $gallery->are_comments_enabled;

            return;
        }

        $defaults = Auth::user()?->shareGalleryDefaults() ?? [];

        $this->selectable = (bool) ($defaults['selectable'] ?? false);
        $this->downloadable = (bool) ($defaults['downloadable'] ?? false);
        $this->selectionLimit = $defaults['selection_limit'] ?? null;
        $this->limitedSelection = (bool) ($defaults['limited_selection'] ?? false);
        $this->passwordProtected = (bool) ($defaults['password_protected'] ?? false);
        $this->watermarked = (bool) ($defaults['watermarked'] ?? false);
        $this->versionsVisible = config('picstome.file_versions_enabled')
            && (bool) ($defaults['versions_visible'] ?? false);
        $this->descriptionEnabled = (bool) ($defaults['description_enabled'] ?? false);
        $this->description = null;
        $this->commentsEnabled = (bool) ($defaults['comments_enabled'] ?? true);
    }

    /**
     * Update the gallery and remember the selected booleans as defaults.
     */
    public function update()
    {
        $this->validate();

        $this->gallery->update([
            'is_share_selectable' => $this->selectable,
            'is_share_downloadable' => $this->downloadable,
            'is_share_watermarked' => $this->watermarked,
            'is_share_versions_visible' => config('picstome.file_versions_enabled') && $this->versionsVisible,
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

        Auth::user()?->rememberShareGalleryDefaults([
            'selectable' => (bool) $this->selectable,
            'downloadable' => (bool) $this->downloadable,
            'limited_selection' => (bool) $this->limitedSelection,
            'selection_limit' => $this->limitedSelection ? $this->selectionLimit : null,
            'password_protected' => (bool) $this->passwordProtected,
            'watermarked' => (bool) $this->watermarked,
            'description_enabled' => (bool) $this->descriptionEnabled,
            'comments_enabled' => (bool) $this->commentsEnabled,
            'versions_visible' => config('picstome.file_versions_enabled') && (bool) $this->versionsVisible,
        ]);
    }
}
