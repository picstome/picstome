<?php

use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Services\FtpInboxIngestor;
use App\Services\GalleryUnlockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Gallery $gallery;

    /**
     * The photographer page asks the server to list the camera inbox; the
     * shared client page only watches the database.
     */
    public bool $scanInbox = false;

    /**
     * On the shared page the watcher removes itself once the photographer
     * stops routing camera uploads to this gallery.
     */
    public bool $stopWhenRemoteModeEnds = false;

    /**
     * The photographer needs the scan to keep running while the tab is in
     * the background; the client only needs it while the page is visible.
     */
    public bool $pollOnlyWhenVisible = true;

    public int $pollSeconds = 5;

    public ?int $lastPhotoId = null;

    public ?int $lastReadyPhotoId = null;

    /**
     * Whether camera uploads are paused because the team has no storage left.
     * The owner page shows it as a banner; the client page hides the live
     * indicator instead of leaking the photographer's billing state.
     */
    public bool $quotaPaused = false;

    public function mount(
        Gallery $gallery,
        bool $scanInbox = false,
        bool $stopWhenRemoteModeEnds = false,
        bool $pollOnlyWhenVisible = true,
    ): void {
        $this->authorizeGallery($gallery);
        $this->pollSeconds = max(1, (int) config('picstome.live_gallery_poll_seconds', 5));
        $this->lastPhotoId = $this->latestPhotoId();
        $this->lastReadyPhotoId = $this->latestReadyPhotoId();
        $this->quotaPaused = $this->activeFtpAccount()?->isQuotaPaused() ?? false;
    }

    public function check(): void
    {
        $account = $this->activeFtpAccount();
        $remoteMode = $account !== null && (int) $account->active_gallery_id === (int) $this->gallery->id;

        // A paused account cannot receive more shots, so the client page must
        // not keep showing a "live" session that will never deliver anything.
        if ($this->stopWhenRemoteModeEnds && (! $remoteMode || $account?->isQuotaPaused())) {
            $this->dispatch('gallery-remote-mode-ended');

            return;
        }

        $this->syncQuotaPause($account);

        if ($this->scanInbox && $remoteMode && $account) {
            $this->scanInboxNow($account);
        }

        $latest = $this->latestPhotoId();
        $latestReady = $this->latestReadyPhotoId();

        if ($latest !== $this->lastPhotoId || $latestReady !== $this->lastReadyPhotoId) {
            $this->lastPhotoId = $latest;
            $this->lastReadyPhotoId = $latestReady;

            $this->dispatch('gallery-photos-updated');
        }
    }

    /**
     * Trigger an inbox scan at most once per poll interval per account.
     * The ingestor also holds a lock, so the cron cannot overlap it.
     */
    protected function scanInboxNow(FtpAccount $account): void
    {
        if (! Cache::add("ftp-live-scan:{$account->id}", true, now()->addSeconds($this->pollSeconds))) {
            return;
        }

        try {
            $ingestor = app(FtpInboxIngestor::class);

            $ingestor->dispatchStableUploads($account, $ingestor->liveScanStabilitySeconds());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function activeFtpAccount(): ?FtpAccount
    {
        return FtpAccount::query()
            ->where('team_id', $this->gallery->team_id)
            ->where('active', true)
            ->whereNotNull('active_gallery_id')
            ->first();
    }

    /**
     * Emit the pause event only on transitions, so the owner page does not
     * re-render the same banner every poll tick.
     */
    protected function syncQuotaPause(?FtpAccount $account): void
    {
        $paused = $account?->isQuotaPaused() ?? false;

        if ($paused === $this->quotaPaused) {
            return;
        }

        $this->quotaPaused = $paused;

        if ($paused) {
            $this->dispatch('ftp-quota-paused');
        }
    }

    protected function latestPhotoId(): ?int
    {
        return $this->gallery->photos()->max('id');
    }

    protected function latestReadyPhotoId(): ?int
    {
        return $this->gallery->photos()->ready()->max('id');
    }

    protected function authorizeGallery(Gallery $gallery): void
    {
        // The photographer and their team can always open their own gallery,
        // even when it is shared behind a password. Without this check the
        // watcher aborts with a 404 before the owner page can render.
        if (Auth::check() && Gate::allows('view', $gallery)) {
            return;
        }

        if ($gallery->is_shared) {
            if ($gallery->share_password && ! app(GalleryUnlockService::class)->isUnlocked($gallery)) {
                abort(404);
            }

            return;
        }

        Gate::authorize('view', $gallery);
    }
}; ?>

<div
    wire:poll{{ $pollOnlyWhenVisible ? '.visible' : '' }}.{{ $pollSeconds }}s="check"
    class="pointer-events-none fixed top-0 left-0 h-px w-px opacity-0"
    aria-hidden="true"
></div>
