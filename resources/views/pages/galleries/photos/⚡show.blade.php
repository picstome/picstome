<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app', ['fullScreen' => true])] class extends Component
{
    use InteractsWithTallStackModals;

    public Photo $photo;

    public ?Photo $next = null;

    public ?Photo $previous = null;

    public $commentText = '';

    public ?float $pendingPinX = null;

    public ?float $pendingPinY = null;

    #[Url]
    public $navigateFavorites = false;

    #[Url]
    public $navigateCommented = false;

    /** Paging through the Archived tab: neighbours stay inside that set. */
    #[Url]
    public $navigateArchived = false;

    public bool $adjustingCover = false;

    public function mount(Photo $photo)
    {
        $this->photo = $photo;

        $this->authorize('view', $this->photo);

        if ($this->navigateFavorites) {
            $this->next = $this->photo->nextFavorite();
            $this->previous = $this->photo->previousFavorite();
        } elseif ($this->navigateCommented) {
            $this->next = $this->photo->nextCommented();
            $this->previous = $this->photo->previousCommented();
        } elseif ($this->navigateArchived) {
            $this->next = $this->photo->nextArchived();
            $this->previous = $this->photo->previousArchived();
        } else {
            $this->next = $this->photo->next();
            $this->previous = $this->photo->previous();
        }

        $this->focusRequestedComment();
    }

    /**
     * Deep link from the guest comment notification: open the comment list and
     * highlight the matching comment.
     */
    protected function focusRequestedComment(): void
    {
        $commentId = (int) request()->query('comment');

        if (! $commentId) {
            return;
        }

        $comment = $this->photo->comments()->whereKey($commentId)->first();

        if (! $comment) {
            return;
        }

        $this->dispatch('focus-comment', id: $comment->id);
        $this->openModal('add-comment');
    }

    public function favorite()
    {
        $this->photo->toggleFavorite();
    }

    /**
     * Toggle the archive state of the current photo and move on, exactly like
     * the F favourite shortcut keeps culling moving.
     *
     * Pressing A on an archived photo restores it and jumps to the next one, so
     * the shortcut is one single "get this out of / back into the selection"
     * key instead of two.
     *
     * Neighbours are captured BEFORE mutating: archiving removes the photo from
     * the live lists and unarchiving removes it from the archived list, so the
     * "next" has to be resolved against the set we are paging through at the
     * moment the key was pressed.
     *
     * It is reversible, so no modal is shown here.
     */
    public function archive()
    {
        $this->authorize('update', $this->photo->gallery);

        $next = $this->next;
        $previous = $this->previous;

        if ($this->photo->isArchived()) {
            $this->photo->unarchive();
        } else {
            $this->photo->archive();

            if ($this->photo->gallery->coverPhoto?->is($this->photo)) {
                $this->photo->gallery->update(['cover_photo_id' => null]);
                $this->photo->gallery->unsetRelation('coverPhoto');
            }
        }

        if ($next) {
            return $this->redirect($this->viewerUrl($next));
        }

        if ($previous) {
            return $this->redirect($this->viewerUrl($previous));
        }

        // There is no neighbour to open: back to the gallery on All photos. The
        // Archived tab cannot be the target because it disappears as soon as
        // the last archived photo is restored, leaving the grid empty.
        return $this->redirect($this->galleryUrlWithoutPhoto());
    }

    /**
     * Restore (unarchive) the current photo without leaving the viewer, used by
     * the dock button when the photographer wants to keep looking at it.
     *
     * Restoring the only archived photo empties the Archived tab, so leaving
     * the viewer on that (now hidden) tab would strand the photographer: go
     * back to All photos instead.
     */
    public function unarchive()
    {
        $this->authorize('update', $this->photo->gallery);

        $this->photo->unarchive();

        // `#[Url]` values arrive as strings, so compare as booleans.
        if (filter_var($this->navigateArchived, FILTER_VALIDATE_BOOLEAN)
            && $this->photo->gallery->archivedPhotoIds()->isEmpty()) {
            return $this->redirect($this->galleryUrlWithoutPhoto());
        }
    }

    /**
     * Gallery URL on its default tab, for when there is no photo to reveal.
     */
    protected function galleryUrlWithoutPhoto(): string
    {
        return route('galleries.show', ['gallery' => $this->photo->gallery_id]);
    }

    public function delete()
    {
        $this->authorize('delete', $this->photo);

        $gallery = $this->photo->gallery;
        $next = $this->next;
        $previous = $this->previous;

        $this->photo->deleteFromDisk()->delete();

        if ($next) {
            return $this->redirect($this->viewerUrl($next));
        }

        if ($previous) {
            return $this->redirect($this->viewerUrl($previous));
        }

        return $this->redirect("/galleries/{$gallery->id}");
    }

    /**
     * Build the viewer URL for an adjacent photo, keeping the active
     * favorites/commented navigation mode.
     */
    protected function viewerUrl(Photo $photo): string
    {
        $query = array_filter([
            'navigateFavorites' => $this->navigateFavorites ? true : null,
            'navigateCommented' => $this->navigateCommented ? true : null,
            'navigateArchived' => $this->navigateArchived ? true : null,
        ]);

        return route('galleries.photos.show', [
            'gallery' => $photo->gallery_id,
            'photo' => $photo->id,
        ] + $query);
    }

    public function setAsCover()
    {
        $this->authorize('updateCover', $this->photo->gallery);

        abort_unless($this->photo->isImage(), 422);

        $this->photo->gallery->setCoverPhoto($this->photo);

        $this->adjustingCover = true;
    }

    /**
     * Remember the clicked position and open the comment composer. The pin is
     * only persisted together with the comment text.
     */
    public function addAnnotation(float $x, float $y): void
    {
        $this->authorize('view', $this->photo);

        abort_unless($this->photo->isImage(), 404);

        $this->pendingPinX = round(max(0, min(100, $x)), 2);
        $this->pendingPinY = round(max(0, min(100, $y)), 2);

        $this->openModal('add-comment');
    }

    public function cancelPendingPin(): void
    {
        $this->pendingPinX = null;
        $this->pendingPinY = null;
    }

    public function addComment()
    {
        $this->validate([
            'commentText' => 'required|string|max:1000',
        ]);

        $hasPin = $this->pendingPinX !== null && $this->pendingPinY !== null && $this->photo->isImage();

        $this->photo->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $this->commentText,
            'x' => $hasPin ? $this->pendingPinX : null,
            'y' => $hasPin ? $this->pendingPinY : null,
            'annotated_version_number' => $hasPin ? $this->photo->currentVersionNumber() : null,
        ]);

        $this->commentText = '';

        if ($hasPin) {
            $this->pendingPinX = null;
            $this->pendingPinY = null;

            $this->closeModal('add-comment');
        }
    }

    public ?int $pendingCommentId = null;

    public function requestDeleteComment(int $id): void
    {
        $this->pendingCommentId = $id;

        $this->openModal('delete-comment');
    }

    public function deleteComment(PhotoComment $comment)
    {
        abort_unless($comment->photo->is($this->photo), 404);

        abort_unless($comment->photo->gallery->team->owner->is(Auth::user()), 403);

        $comment->delete();
    }

    public function removeAsCover()
    {
        $this->authorize('updateCover', $this->photo->gallery);

        if ($this->photo->gallery->coverPhoto?->is($this->photo)) {
            $this->photo->gallery->removeCoverPhoto();
        }
    }

    public function setCoverFocalPoint(float $x, float $y): void
    {
        $this->authorize('updateCover', $this->photo->gallery);

        $this->photo->gallery->setCoverFocalPoint($x, $y);
    }

    #[Computed]
    public function galleryUrl()
    {
        // `#[Url]` properties arrive as strings ("true"/"1"), so `match (true)`
        // (strict comparison) needs real booleans.
        $favorites = filter_var($this->navigateFavorites, FILTER_VALIDATE_BOOLEAN);
        $commented = filter_var($this->navigateCommented, FILTER_VALIDATE_BOOLEAN);
        $archived = filter_var($this->navigateArchived, FILTER_VALIDATE_BOOLEAN);

        $tab = match (true) {
            $favorites => 'favorited',
            $commented => 'commented',
            $archived => 'archived',
            default => null,
        };

        $query = array_filter([
            'activeTab' => $tab,
            'photo' => $this->photo->id,
        ]);

        $anchor = match (true) {
            $favorites => 'favorite-',
            $commented => 'commented-',
            $archived => 'archived-',
            default => 'photo-',
        };

        return route('galleries.show', ['gallery' => $this->photo->gallery] + $query)
            .'#'.$anchor.$this->photo->id;
    }

    #[Computed]
    public function comments()
    {
        return $this->photo->comments()->latest()->with('user')->get();
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
}; ?>

<div
    x-data="{
        zoom: false,
        scale: 1,
        translateX: 0,
        translateY: 0,
        navigating: false,
        gestureActive: false,
        gestureBlocked: false,
        gestureHadMultipleTouches: false,
        swipeStartX: null,
        swipeStartY: null,
        swipeStartedAt: 0,
        pinchStartDistance: 0,
        pinchStartScale: 1,
        panStartX: 0,
        panStartY: 0,
        panStartTranslateX: 0,
        panStartTranslateY: 0,
        initPhotoViewer() {
            const source = this.$el.dataset.fullImageUrl
            if (source) {
                const image = new Image()
                image.src = source
            }
        },
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                navigator.userAgent,
            )
        },
        onTouchStart(event) {
            if (event.target.closest('button, a, input, textarea, select, [data-no-gesture]')) {
                this.gestureBlocked = true

                return
            }

            this.gestureBlocked = false
            this.gestureActive = true

            if (event.touches.length >= 2) {
                this.gestureHadMultipleTouches = true
                this.swipeStartX = null
                this.swipeStartY = null

                const first = event.touches[0]
                const second = event.touches[1]
                this.pinchStartDistance = Math.hypot(
                    first.clientX - second.clientX,
                    first.clientY - second.clientY,
                ) || 1
                this.pinchStartScale = this.scale
                this.zoom = true

                return
            }

            if (event.touches.length === 1) {
                const touch = event.touches[0]

                if (this.scale > 1.01) {
                    this.zoom = true
                    this.panStartX = touch.clientX
                    this.panStartY = touch.clientY
                    this.panStartTranslateX = this.translateX
                    this.panStartTranslateY = this.translateY
                } else {
                    this.swipeStartX = touch.clientX
                    this.swipeStartY = touch.clientY
                    this.swipeStartedAt = Date.now()
                }
            }
        },
        onTouchMove(event) {
            if (this.gestureBlocked || ! this.gestureActive) {
                return
            }

            if (event.touches.length >= 2 && this.pinchStartDistance > 0) {
                const first = event.touches[0]
                const second = event.touches[1]
                const distance = Math.hypot(
                    first.clientX - second.clientX,
                    first.clientY - second.clientY,
                )
                this.scale = Math.min(
                    4,
                    Math.max(1, this.pinchStartScale * (distance / this.pinchStartDistance)),
                )
                this.zoom = this.scale > 1.01

                if (this.scale <= 1.01) {
                    this.translateX = 0
                    this.translateY = 0
                }

                return
            }

            if (event.touches.length === 1 && this.scale > 1.01) {
                const touch = event.touches[0]
                this.translateX = this.panStartTranslateX + (touch.clientX - this.panStartX)
                this.translateY = this.panStartTranslateY + (touch.clientY - this.panStartY)
            }
        },
        onTouchEnd(event) {
            if (this.gestureBlocked) {
                this.resetGesture()

                return
            }

            if (event.touches.length > 0) {
                if (this.scale > 1.01 && event.touches[0]) {
                    this.panStartX = event.touches[0].clientX
                    this.panStartY = event.touches[0].clientY
                    this.panStartTranslateX = this.translateX
                    this.panStartTranslateY = this.translateY
                }

                this.swipeStartX = null
                this.swipeStartY = null

                return
            }

            const wasMulti = this.gestureHadMultipleTouches
            const touch = event.changedTouches[0]
            const startX = this.scale > 1.01 ? this.panStartX : this.swipeStartX
            const startY = this.scale > 1.01 ? this.panStartY : this.swipeStartY
            const dx = touch && startX !== null ? touch.clientX - startX : 0
            const dy = touch && startY !== null ? touch.clientY - startY : 0
            const duration = Date.now() - this.swipeStartedAt

            if (this.scale > 1.01 && Math.abs(dx) < 10 && Math.abs(dy) < 10 && ! wasMulti) {
                this.resetZoom()
            }

            this.resetGesture()

            if (wasMulti || this.scale > 1.01 || this.zoom) {
                return
            }

            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5 || duration > 800) {
                return
            }

            this.navigatePhoto(dx < 0 ? 'next' : 'previous')
        },
        resetGesture() {
            this.gestureActive = false
            this.gestureBlocked = false
            this.gestureHadMultipleTouches = false
            this.swipeStartX = null
            this.swipeStartY = null
            this.swipeStartedAt = 0
            this.pinchStartDistance = 0
        },
        resetZoom() {
            this.zoom = false
            this.scale = 1
            this.translateX = 0
            this.translateY = 0
        },
        toggleZoom() {
            if (this.scale > 1.01) {
                this.resetZoom()

                return
            }

            this.zoom = true
            this.scale = 2
        },
        navigatePhoto(direction) {
            if (this.navigating) {
                return
            }

            const ref = direction === 'next' ? this.$refs.next : this.$refs.previous

            if (! ref) {
                return
            }

            this.navigating = true
            Livewire.navigate(ref.href)
            setTimeout(() => { this.navigating = false }, 500)
        },
        requestDelete(event) {
            if (
                event.target instanceof Element &&
                event.target.closest('input, textarea, select, [contenteditable]')
            ) {
                return
            }

            event.preventDefault()
            this.$dispatch('modal:delete-photo-open')
        },
        archiveByKey(event) {
            // Never hijack typing: comments and annotation inputs must win.
            const target = event?.target

            if (target instanceof Element && target.closest('input, textarea, select, [contenteditable]')) {
                return
            }

            if (event?.metaKey || event?.ctrlKey || event?.altKey) {
                return
            }

            $wire.archive()
        },
    }"
    x-init="initPhotoViewer()"
    data-full-image-url="{{ $photo->url }}"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    @keyup.window.f="$wire.favorite()"
    @keyup.window.a="archiveByKey($event)"
    @keyup.window.delete="requestDelete($event)"
    @keyup.window.backspace="requestDelete($event)"
    x-on:modal-close.document="if ($event.detail.name === 'add-comment') $wire.cancelPendingPin()"
    style="height: 100vh; height: 100dvh;"
    class="flex flex-col"
>
    <div
        id="photo"
        class="relative flex h-full flex-1 overflow-hidden"
        style="touch-action: none;"
        @touchstart="onTouchStart($event)"
        @touchmove.prevent="onTouchMove($event)"
        @touchend="onTouchEnd($event)"
        @touchcancel="onTouchEnd($event)"
    >
        @if ($photo->isImage())
            <img
                src="{{ $photo->thumbnail_url }}"
                srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                sizes="(max-width: 640px) 100vw, 80vw"
                style="background-image: url('{{ $photo->small_thumbnail_url }}'); background-size: cover; background-repeat: no-repeat; background-position: center;"
                x-data="{ loaded: false, errored: false }"
                x-init="if ($el.complete) { loaded = true; $el.style.backgroundImage = 'none' }"
                x-show="!zoom"
                x-on:load="loaded = true; $el.style.backgroundImage = 'none'"
                x-on:error="errored = true"
                @click="if (!isMobile()) toggleZoom()"
                :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60 h-full w-full'"
                class="mx-auto max-w-full object-contain hover:cursor-zoom-in"
                alt="{{ $photo->name }}"
            />

            <img
                src="{{ $photo->url }}"
                style="background-image: url('{{ $photo->small_thumbnail_url }}'); background-size: cover; background-repeat: no-repeat; background-position: center;"
                x-data="{ loaded: false, errored: false }"
                x-init="if ($el.complete) { loaded = true; $el.style.backgroundImage = 'none' }"
                x-show="zoom"
                x-ref="fullImage"
                x-bind:style="{ transform: 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + scale + ')' }"
                x-on:load="loaded = true; $el.style.backgroundImage = 'none'"
                x-on:error="errored = true"
                @click="if (!isMobile()) resetZoom()"
                class="mx-auto max-h-full max-w-full object-contain hover:cursor-zoom-out"
                :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60 h-full w-full'"
                alt="{{ $photo->name }}"
                x-cloak
                data-full-image
            />

            <x-photo-annotations
                :photo="$photo"
                :comments="$this->comments"
                :can-annotate="true"
            />
        @elseif ($photo->isVideo())
            <video
                x-data="{
                    key: 'video-pos-{{ $photo->id }}',
                    savePosition(e) {
                        if (!e.target.seeking && !e.target.paused) {
                            localStorage.setItem(this.key, e.target.currentTime);
                        }
                    },
                    restorePosition(e) {
                        const saved = localStorage.getItem(this.key);
                        if (saved && !isNaN(saved) && saved > 0 && saved < e.target.duration - 2) {
                            e.target.currentTime = parseFloat(saved);
                        }
                    },
                    clearPosition() {
                        localStorage.removeItem(this.key);
                    }
                }"
                x-init="
                    $el.addEventListener('loadedmetadata', restorePosition)
                    $el.addEventListener('timeupdate', savePosition)
                    $el.addEventListener('ended', clearPosition)
                "
                class="mx-auto h-full w-full max-w-full bg-black/60 object-contain dark:bg-white/60"
                controls
                autoplay
                muted
                playsinline
            >
                <source
                    src="{{ $photo->url }}"
                    type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}"
                />
                Your browser does not support the video tag.
            </video>
        @elseif ($photo->isPdf())
            <div class="flex h-full w-full flex-col items-center justify-center gap-4 px-8 text-center">
                <x-icon name="document-text" class="size-16 text-zinc-400 dark:text-white/50" />
                <x-ts-heading size="lg" class="max-w-full truncate">{{ $photo->name }}</x-ts-heading>
                <x-ts-text>{{ $photo->formattedSize() }}</x-ts-text>
                <x-ts-button
                    :href="route('galleries.photos.inline', ['gallery' => $photo->gallery, 'photo' => $photo])"
                    icon="arrow-top-right-on-square"
                    target="_blank"
                    variant="primary"
                >
                    {{ __('Open PDF') }}
                </x-ts-button>
            </div>
        @else
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
        @endif

        {{-- Keep only the back affordance floating at the top: every action
             now lives in the bottom dock, so the photo is not boxed in. --}}
        <div
            class="absolute top-0 right-0 left-0 z-30 flex items-center justify-between gap-4 p-3 max-sm:p-1"
            :class="zoom ? 'hidden' : 'flex'"
        >
            <x-ts-button
                :href="$this->galleryUrl"
                icon="arrow-left"
                icon:variant="micro"
                size="sm"
                variant="primary"
                class="btn-ink"
                data-photo-back
                wire:navigate
                :tooltip="__('Back to gallery :name', ['name' => $photo->gallery->name])"
                :aria-label="__('Back to gallery :name', ['name' => $photo->gallery->name])"
            >
                {{ __('Back') }}
            </x-ts-button>

            <x-photo-info :$photo :facts="$photo->infoFacts()" />
        </div>

        <x-closeup-panel
            :photo="$photo"
            :image-url="$photo->large_thumbnail_url"
            :full-url="$photo->url"
            :original-url="$photo->original_url"
        />

        {{-- Bottom dock: one icon-only button per action, grouped by meaning.
             Destructive actions are last and tinted red, so nothing can be hit
             by accident while culling. --}}
        <x-photo-dock
            x-show="! zoom"
            style="display: none"
            data-viewer-dock
        >
            {{-- Navigation first: previous / next live here now, so the photo
                 is not framed by two floating arrows. x-ref stays so the
                 arrow-key shortcuts keep navigating. --}}
            @if ($previous)
                <x-dock-button
                    href="/galleries/{{ $photo->gallery->id }}/photos/{{ $previous->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : ($navigateCommented ? '?navigateCommented=true' : ($navigateArchived ? '?navigateArchived=true' : '')) }}"
                    icon="chevron-left"
                    :label="__('Previous photo')"
                    hint="←"
                    x-ref="previous"
                    wire:navigate
                />
            @endif

            @if ($next)
                <x-dock-button
                    href="/galleries/{{ $photo->gallery->id }}/photos/{{ $next->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : ($navigateCommented ? '?navigateCommented=true' : ($navigateArchived ? '?navigateArchived=true' : '')) }}"
                    icon="chevron-right"
                    :label="__('Next photo')"
                    hint="→"
                    x-ref="next"
                    wire:navigate
                />
            @endif

            <x-dock-divider />

            <x-dock-button
                icon="heart"
                :label="__('Favorite')"
                hint="F"
                :tone="$photo->isFavorited() ? 'favorite' : 'default'"
                wire:click="favorite"
                data-dock-favorite
            />

            @if ($photo->isImage() && ! $photo->gallery->coverPhoto?->is($photo))
                <x-dock-button icon="star" :label="__('Set as Cover')" wire:click="setAsCover" />
            @elseif ($photo->isImage())
                {{-- Already the cover: one button opens both cover actions so
                     nothing is lost when the dock replaced the old dropdown. --}}
                <x-dropdown position="top-end">
                    <x-slot:action>
                        <x-dock-button icon="star" :label="__('Cover actions')" tone="active" x-on:click="show = ! show" />
                    </x-slot:action>

                    <x-dropdown.items
                        :text="__('Adjust Cover Focus')"
                        icon="viewfinder-circle"
                        wire:click="$set('adjustingCover', true)"
                    />
                    <x-dropdown.items
                        :text="__('Remove as Cover')"
                        icon="x-mark"
                        wire:click="removeAsCover"
                    />
                </x-dropdown>
            @endif

            <x-dock-divider />

            <x-closeup-panel-button hint="C" />

            <x-ts-modal-trigger name="add-comment">
                <x-dock-button
                    icon="chat-bubble-left-ellipsis"
                    :label="__('Comments (:count)', ['count' => $this->comments->count()])"
                    :tone="$this->comments->isEmpty() ? 'default' : 'ink'"
                    data-photo-comments
                />
            </x-ts-modal-trigger>

            <x-photo-annotate-button
                :can-annotate="$photo->isImage()"
                :has-annotations="$this->annotationsCount > 0"
            />

            @if ($this->annotationsCount > 0)
                <x-photo-annotations-toggle />
            @endif

            <x-dock-divider />

            @if (config('picstome.file_versions_enabled'))
                <livewire:photo-versions :photo="$photo" :manageable="true" />
            @endif

            @if ($photo->path && $photo->raw_path)
                <x-dropdown position="top-end">
                    <x-slot:action>
                        <x-dock-button
                            icon="cloud-arrow-down"
                            :label="__('Download')"
                            x-on:click="show = ! show"
                        />
                    </x-slot:action>

                    <x-dropdown.items
                        :text="__('Download JPG')"
                        icon="cloud-arrow-down"
                        :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'jpg'])"
                    />
                    <x-dropdown.items
                        :text="__('Download Raw')"
                        icon="cloud-arrow-down"
                        :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'raw'])"
                    />
                </x-dropdown>
            @else
                <x-dock-button
                    icon="cloud-arrow-down"
                    :label="__('Download')"
                    :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                />
            @endif

            {{-- Archive sits immediately left of Delete, so the two "get it out
                 of the client's view" actions are next to each other. --}}
            <x-dock-button
                icon="archive-box"
                :label="$photo->isArchived() ? __('Unarchive') : __('Archive')"
                hint="A"
                :tone="$photo->isArchived() ? 'active' : 'default'"
                wire:click="{{ $photo->isArchived() ? 'unarchive' : 'archive' }}"
                data-photo-archive
            />

            <x-dock-button
                icon="trash"
                :label="__('Delete')"
                hint="DEL"
                tone="danger"
                data-photo-delete
                data-photo-delete-mobile
                x-on:click="$dispatch('modal:delete-photo-open')"
            />
        </x-photo-dock>
    </div>

    <x-ts-modal name="add-comment" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Comments') }}</x-ts-heading>
                <x-ts-subheading>{{ __('All comments for this photo.') }}</x-ts-subheading>
            </div>

            @if ($this->staleAnnotationsCount > 0)
                <div
                    class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    {{ __('Annotations from older versions: :count', ['count' => $this->staleAnnotationsCount]) }}
                </div>
            @endif

            @if ($this->comments->isNotEmpty())
                <div class="max-h-64 space-y-4 overflow-y-auto">
                    @foreach ($this->comments as $comment)
                        <x-photo-comment-item
                            :comment="$comment"
                            :can-delete="true"
                            :align-right="$comment->user && $comment->user->is($photo->gallery->team->owner)"
                            :current-version-number="$this->currentVersionNumber"
                            :version-numbers="$this->versionNumbers"
                        />
                    @endforeach
                </div>
            @endif

            <form wire:submit="addComment" class="space-y-4 pt-2">
                <x-ts-textarea wire:model.defer="commentText" :label="__('Add a comment')" rows="3" />

                <div class="flex">
                    <x-ts-spacer />
                    <x-ts-button type="submit" variant="primary">{{ __('Submit') }}</x-ts-button>
                </div>
            </form>
        </div>
    </x-ts-modal>

    <x-confirm-modal
        name="delete-photo"
        :title="__('Delete photo?')"
        :message="__('This action cannot be undone.')"
        method="delete"
    />

    <x-confirm-modal
        name="delete-comment"
        :title="__('Delete comment?')"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingCommentId ? 'deleteComment(' . $this->pendingCommentId . ')' : null"
    />

    <x-cover-focus-overlay :gallery="$photo->gallery" :photo="$photo" :adjusting="$adjustingCover" />
</div>

@script
    <script>
        @include('partials.closeup-panel-script')
    </script>
@endscript

@push('head')
    @if ($photo->isImage())
        <link rel="preload" as="image" href="{{ $photo->url }}" />
    @endif

    @if ($next && $next->isImage())
        <link rel="preload" as="image" href="{{ $next->url }}" />
        <link rel="preload" as="image" href="{{ $next->thumbnail_url }}" />
        <link rel="preload" as="image" href="{{ $next->large_thumbnail_url }}" />
    @endif

    @if ($previous && $previous->isImage())
        <link rel="preload" as="image" href="{{ $previous->url }}" />
        <link rel="preload" as="image" href="{{ $previous->thumbnail_url }}" />
        <link rel="preload" as="image" href="{{ $previous->large_thumbnail_url }}" />
    @endif
@endpush
</div>
