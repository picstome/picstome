<?php

namespace App\Traits\Gallery;

use App\Models\Photo;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

/**
 * Photo-level actions on the owner gallery: delete, favorite, archive and cover.
 */
trait ManagesGalleryPhotos
{
    #[On('request-delete-photo')]
    public function requestDeletePhoto(int $id): void
    {
        $this->pendingPhotoId = $id;

        $this->openModal('delete-photo');
    }

    public function deletePhoto(Photo $photo)
    {
        $this->authorize('delete', $photo);

        $photo->deleteFromDisk()->delete();

        $this->pendingPhotoId = null;

        $this->forgetPhotoCollections();
    }

    public function toggleFavorite(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $photo->toggleFavorite();

        $this->forgetPhotoCollections();

        $this->dispatch('photo-favorited');
        $this->dispatch(
            'favorite-toggled',
            id: $photo->id,
            favorited: $photo->isFavorited(),
        );
    }

    /**
     * Archive or restore one photo from the gallery grid.
     *
     * Archiving is reversible, so it needs no confirmation: the individual
     * action is instant, exactly like favoriting. Only the destructive
     * "delete all archived" goes through a modal.
     */
    public function toggleArchive(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $this->authorize('update', $this->gallery);

        $photo->toggleArchive();

        // A cover photo that is archived would leak back into the public page.
        if ($photo->isArchived() && $this->gallery->coverPhoto?->is($photo)) {
            $this->gallery->update(['cover_photo_id' => null]);
            $this->gallery->unsetRelation('coverPhoto');
        }

        $this->forgetPhotoCollections();
    }

    /**
     * Ask for confirmation before deleting every archived photo. The modal
     * states that the action is NOT reversible, so it is never auto-run.
     */
    public function requestDeleteArchived(): void
    {
        $this->authorize('update', $this->gallery);

        $this->pendingArchivedCount = $this->archivedIds->count();

        if ($this->pendingArchivedCount === 0) {
            return;
        }

        $this->openModal('delete-archived');
    }

    public function deleteArchived(): void
    {
        $this->authorize('update', $this->gallery);

        $this->gallery->photos()->archived()->with('versions')->cursor()->each(
            fn (Photo $photo) => $photo->deleteFromDisk()->delete()
        );

        $this->pendingArchivedCount = 0;
        $this->activeTab = 'all';

        $this->forgetPhotoCollections();

        $this->closeModal('delete-archived');

        $this->toast()->success(__('All archived photos were deleted.'))->send();
    }

    public function setAsCover(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $this->authorize('updateCover', $this->gallery);

        abort_unless($photo->isImage(), 422);

        $this->gallery->setCoverPhoto($photo);
        $this->gallery->unsetRelation('coverPhoto');
        $this->gallery->load('coverPhoto');

        $this->forgetPhotoCollections();

        $this->dispatch('gallery-cover-changed');
        $this->adjustingCover = true;
    }

    public function removeAsCover(Photo $photo): void
    {
        abort_unless($photo->gallery_id === $this->gallery->id, 404);

        $this->authorize('updateCover', $this->gallery);

        if ($this->gallery->coverPhoto?->is($photo)) {
            $this->gallery->removeCoverPhoto();
            $this->gallery->load('coverPhoto');

            $this->forgetPhotoCollections();

            $this->dispatch('gallery-cover-changed');
        }
    }

    public function setCoverFocalPoint(float $x, float $y): void
    {
        $this->authorize('updateCover', $this->gallery);

        $this->gallery->setCoverFocalPoint($x, $y);
        $this->gallery->load('coverPhoto');

        $this->forgetPhotoCollections();

        $this->dispatch('gallery-cover-changed');
    }

    public function markFavorites()
    {
        $this->validate([
            'favoriteFileNames' => ['required', 'string'],
        ]);

        $baseNames = collect(explode("\n", $this->favoriteFileNames))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->map(fn ($name) => pathinfo($name, PATHINFO_FILENAME))
            ->filter()
            ->values();

        if ($baseNames->isEmpty()) {
            return;
        }

        $this->gallery->photos()
            ->whereNull('favorited_at')
            ->where(function ($query) use ($baseNames) {
                $baseNames->each(fn ($base) => $query->orWhere('name', 'LIKE', $base.'.%'));
            })
            ->update(['favorited_at' => now(), 'rating' => 5]);

        Cache::forget("gallery:{$this->gallery->id}:favorites:ids");
        Photo::invalidateGalleryQueryCache((int) $this->gallery->id);

        $this->forgetPhotoCollections();
        $this->favoriteFileNames = '';

        $this->closeModal('mark-favorites');

        $this->dispatch('photo-favorited');
    }
}
