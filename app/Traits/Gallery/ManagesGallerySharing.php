<?php

namespace App\Traits\Gallery;

use App\Models\FtpAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Gallery sharing, public visibility and camera (FTP) upload settings.
 */
trait ManagesGallerySharing
{
    public function share()
    {
        $this->shareForm->gallery->is_shared = true;

        $this->shareForm->update();

        $this->closeModal('share');

        $this->openModal('share-link');

        $this->gallery = $this->gallery->fresh();

        $this->dispatch('gallery-sharing-changed');
    }

    public function disableSharing()
    {
        $this->gallery->update(['is_shared' => false]);

        $this->dispatch('gallery-sharing-changed');
    }

    public function togglePublic()
    {
        $this->gallery->togglePublic();

        $this->dispatch('gallery-public-changed');
    }

    /**
     * The live watcher detected that camera uploads were paused because the
     * team ran out of storage. Refresh the storage figures and surface the
     * banner so the photographer knows before the next shot.
     */
    #[On('ftp-quota-paused')]
    public function handleQuotaPaused(): void
    {
        $team = $this->gallery->team;

        $this->remainingStorageBytes = $team->remainingStorageBytes(fresh: true);

        unset($this->ftpAccount);
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function ftpAccount(): ?FtpAccount
    {
        return FtpAccount::where('team_id', $this->gallery->team_id)->first();
    }

    public function toggleFtpPassword()
    {
        $this->showFtpPassword = ! $this->showFtpPassword;
    }

    /**
     * Start or stop routing camera uploads into this gallery. The team's
     * credentials never change, so cameras stay configured.
     */
    public function toggleFtpUploads()
    {
        $this->authorize('update', $this->gallery);

        // Camera upload is available on every plan, but a team with no space
        // left has nothing to route to: the ingestor would discard the shots.
        if (! $this->gallery->team->hasStorageLeft()) {
            $this->toast()
                ->error(__('Camera uploads are paused: no storage left.'))
                ->send();

            return;
        }

        if (! $account = $this->ftpAccount) {
            $account = FtpAccount::provisionForTeam($this->gallery->team);

            if (! $account) {
                $this->toast()->error(__('Could not reach the upload server. Please try again.'))->send();

                return;
            }
        }

        $routed = $account->routesTo($this->gallery);

        $account->routeTo($routed ? null : $this->gallery);

        if (! $routed) {
            // Live sessions deliver camera originals, so they follow the same
            // preference used when creating a gallery.
            $this->gallery->update(['keep_original_size' => $this->keepOriginalSize]);
        }

        unset($this->ftpAccount);
    }

    /**
     * Live sessions upload camera originals, so the gallery stores them at
     * their original size unless the photographer turns it off. The choice is
     * remembered as the default for the next gallery as well, so the create
     * gallery modal keeps showing the same preference.
     */
    public function updatedKeepOriginalSize(bool $value): void
    {
        $this->authorize('update', $this->gallery);

        $this->gallery->update(['keep_original_size' => $value]);

        // Keep the edit gallery modal in sync so it never shows a stale value.
        $this->form->keepOriginalSize = $value;

        Auth::user()?->rememberGalleryDefaults(['keep_original_size' => $value]);
    }
}
