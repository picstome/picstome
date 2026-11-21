<?php

namespace App\Traits\Concerns;

use App\Models\PhotoComment;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

/**
 * Comment list, video markers and version helpers shared by the owner and the
 * shared photo viewers. The authorization-sensitive actions stay in each
 * component because their rules differ.
 */
trait ManagesPhotoViewer
{
    public function cancelPendingPin(): void
    {
        $this->pendingPinX = null;
        $this->pendingPinY = null;
    }

    public function cancelPendingTime(): void
    {
        $this->pendingTimeSeconds = null;
    }

    public function requestDeleteComment(int $id): void
    {
        $this->pendingCommentId = $id;

        $this->openModal('delete-comment');
    }

    #[Computed]
    public function comments(): Collection
    {
        return $this->photo->comments()->latest()->with('user')->get();
    }

    /**
     * The player draws markers from its initial render only; this pushes the
     * new list after a comment is written or deleted.
     */
    protected function broadcastVideoMarkers(): void
    {
        if (! $this->photo->isVideo()) {
            return;
        }

        unset($this->comments, $this->videoMarkers);

        $this->dispatch(
            'video-markers-updated',
            photoId: $this->photo->id,
            markers: $this->videoMarkers,
        );
    }

    /**
     * Markers for the video rail, keyed by comment. Pushed on
     * `video-markers-updated` after a comment write because Alpine does not
     * re-evaluate `x-data` on a Livewire morph.
     *
     * @return list<array{id: int, time: float, label: string, aria: string}>
     */
    #[Computed]
    public function videoMarkers(): array
    {
        return $this->comments
            ->filter(fn (PhotoComment $comment) => $comment->hasTimestamp())
            ->map(fn (PhotoComment $comment) => [
                'id' => $comment->id,
                'time' => (float) $comment->time_seconds,
                'label' => $comment->timecode(),
                'aria' => __('Jump to :time', ['time' => $comment->timecode()]),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function currentVersionNumber(): int
    {
        return $this->photo->currentVersionNumber();
    }

    /**
     * Version numbers still stored for this photo, used to tell whether an
     * annotation points to a version that was deleted or collapsed.
     *
     * @return list<int>
     */
    #[Computed]
    public function versionNumbers(): array
    {
        return $this->photo->versions()
            ->pluck('version_number')
            ->map(fn ($number) => (int) $number)
            ->values()
            ->all();
    }

    #[Computed]
    public function annotationsCount(): int
    {
        return $this->comments
            ->filter(fn (PhotoComment $comment) => $comment->hasCoordinates())
            ->count();
    }

    #[Computed]
    public function staleAnnotationsCount(): int
    {
        return $this->comments
            ->filter(fn (PhotoComment $comment) => $comment->hasCoordinates()
                && $comment->isFromOtherVersion($this->currentVersionNumber))
            ->count();
    }
}
