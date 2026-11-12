<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Notifications\GuestPhotoCommented;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.guest', ['fullScreen' => true])] class extends Component
{
    use InteractsWithTallStackModals;

    public Photo $photo;

    public Gallery $gallery;

    public ?Photo $next;

    public ?Photo $previous;

    #[Url]
    public $navigateFavorites = false;

    #[Url]
    public $navigateCommented = false;

    public $commentText = '';

    public ?float $pendingPinX = null;

    public ?float $pendingPinY = null;

    /**
     * Playhead position captured when the comment composer opens on a video,
     * stored with the comment as `time_seconds`.
     */
    public ?float $pendingTimeSeconds = null;

    /**
     * Second the player starts at when a deep link points at a timestamped
     * comment. Wins over the remembered playback position.
     */
    public ?float $seekToSeconds = null;

    public function mount()
    {
        abort_unless($this->gallery->is_shared, 404);

        abort_unless($this->photo->gallery_id === $this->gallery->id, 404);

        // Archived photos are invisible to clients, so their shared link must
        // not resolve either.
        abort_if($this->photo->isArchived(), 404);

        if ($this->navigateFavorites) {
            $this->next = $this->photo->nextFavorite();
            $this->previous = $this->photo->previousFavorite();
        } elseif ($this->navigateCommented) {
            $this->next = $this->photo->nextCommented();
            $this->previous = $this->photo->previousCommented();
        } else {
            $this->next = $this->photo->next();
            $this->previous = $this->photo->previous();
        }

        $this->focusRequestedComment();
    }

    /**
     * Deep link from the comment notification: open the comment list, highlight
     * the matching comment and, for a timestamped comment, start the video at
     * that exact second.
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

        if ($comment->hasTimestamp()) {
            $this->seekToSeconds = (float) $comment->time_seconds;
        }

        $this->openModal('add-comment');
    }

    public function favorite()
    {
        abort_unless($this->photo->gallery->is_share_selectable, 401);

        if ($this->photo->toggleFavoriteAndCheckSelectionLimit()) {
            $this->dispatch('selection-limit-reached');

            $this->photo->gallery->notifyOwnerWhenSelectionLimitReached();
        }
    }

    #[Computed]
    public function galleryUrl()
    {
        $query = array_filter([
            'activeTab' => $this->navigateFavorites ? 'favorited' : ($this->navigateCommented ? 'commented' : null),
            'photo' => $this->photo->id,
        ]);

        $anchor = $this->navigateFavorites ? 'favorite-' : ($this->navigateCommented ? 'commented-' : 'photo-');

        return route('shares.show', ['gallery' => $this->photo->gallery, 'slug' => $this->photo->gallery->slug] + $query)
            .'#'.$anchor.$this->photo->id;
    }

    /**
     * Remember the clicked position and open the comment composer. The pin is
     * only persisted together with the comment text.
     */
    public function addAnnotation(float $x, float $y): void
    {
        abort_unless($this->photo->isImage(), 404);

        abort_unless($this->photo->gallery->are_comments_enabled, 403);

        $isOwner = Auth::check() && $this->photo->gallery->team->owner->is(Auth::user());

        abort_if(Auth::check() && ! $this->canManageComments, 403);

        abort_unless($isOwner || $this->photo->gallery->are_annotations_enabled, 403);

        $this->pendingPinX = round(max(0, min(100, $x)), 2);
        $this->pendingPinY = round(max(0, min(100, $y)), 2);

        $this->openModal('add-comment');
    }

    public function cancelPendingPin(): void
    {
        $this->pendingPinX = null;
        $this->pendingPinY = null;
    }

    /**
     * Anchor the comment being written to the current playhead. Capture is
     * video-only: image comments use `addAnnotation` instead.
     */
    public function setPendingTime(float $seconds): void
    {
        abort_unless($this->photo->isVideo(), 404);

        abort_unless($this->photo->gallery->are_comments_enabled, 403);

        $this->pendingTimeSeconds = round(max(0, $seconds), 2);
    }

    public function cancelPendingTime(): void
    {
        $this->pendingTimeSeconds = null;
    }

    public function addComment()
    {
        abort_unless($this->photo->gallery->are_comments_enabled, 403);

        $this->validate([
            'commentText' => 'required|string|max:1000',
        ]);

        abort_if(Auth::check() && ! $this->canManageComments, 403);

        $hasPin = $this->pendingPinX !== null && $this->pendingPinY !== null && $this->photo->isImage();
        $hasTimestamp = $this->pendingTimeSeconds !== null && $this->photo->isVideo();

        $comment = $this->photo->comments()->create([
            'user_id' => Auth::id() ?: null,
            'comment' => $this->commentText,
            'x' => $hasPin ? $this->pendingPinX : null,
            'y' => $hasPin ? $this->pendingPinY : null,
            'time_seconds' => $hasTimestamp ? $this->pendingTimeSeconds : null,
            'annotated_version_number' => $hasPin || $hasTimestamp ? $this->photo->currentVersionNumber() : null,
        ]);

        if (Auth::guest()) {
            $this->photo
                ->gallery
                ->team->owner->notify(new GuestPhotoCommented($this->photo, $comment));
        }

        $this->commentText = '';

        if ($hasPin) {
            $this->pendingPinX = null;
            $this->pendingPinY = null;

            $this->closeModal('add-comment');
        }

        if ($hasTimestamp) {
            $this->pendingTimeSeconds = null;

            $this->closeModal('add-comment');
        }

        $this->broadcastVideoMarkers();
    }

    public ?int $pendingCommentId = null;

    public function requestDeleteComment(int $id): void
    {
        $this->pendingCommentId = $id;

        $this->openModal('delete-comment');
    }

    public function deleteComment(PhotoComment $comment)
    {
        abort_unless($this->photo->gallery->are_comments_enabled, 403);

        abort_unless($comment->photo->is($this->photo), 404);

        abort_unless($this->canManageComments, 403);

        $comment->delete();

        $this->broadcastVideoMarkers();
    }

    /**
     * Whether the authenticated visitor can comment, annotate and moderate.
     * The team owner keeps this ability; platform admins can help on any
     * shared gallery without impersonating the studio.
     */
    #[Computed]
    public function canManageComments(): bool
    {
        $user = Auth::user();

        return $user !== null
            && ($user->is_admin || $this->photo->gallery->team->owner->is($user));
    }

    #[Computed]
    public function comments()
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
}; ?>

@php
    $watermarkTeam = $photo->gallery->team;
    $watermarkSize = config('picstome.watermark_sizes.'.($watermarkTeam->brand_watermark_size ?: 'md'), config('picstome.watermark_sizes.md'));
    $watermarkFontPx = (int) ($watermarkSize['px'] ?? 26);
    $watermarkFontFamily = $watermarkTeam->brand_watermark_font
        ? "'{$watermarkTeam->brand_watermark_font}', sans-serif"
        : 'ui-sans-serif, system-ui, sans-serif';
    $watermarkLightboxFontSize = 'clamp('.(int) round($watermarkFontPx * 0.75).'px, '.round($watermarkFontPx / 12, 4).'vw, '.(int) round($watermarkFontPx * 2.5).'px)';
    $watermarkRepeatedFontPx = (int) round($watermarkFontPx * 1.75);
@endphp

<x-slot name="font">{{ $photo->gallery->team->brand_font }}</x-slot>

<x-slot name="color">{{ $photo->gallery->team->brand_color }}</x-slot>

<x-slot name="watermarkFont">{{ $watermarkTeam->isTextWatermark() ? $watermarkTeam->brand_watermark_font : '' }}</x-slot>

<div
    x-data="{
        previousThumbnailUrl: '{{ $previous?->thumbnail_url }}',
        nextThumbnailUrl: '{{ $next?->thumbnail_url }}',
        previousLargeThumbnailUrl: '{{ $previous?->large_thumbnail_url }}',
        nextLargeThumbnailUrl: '{{ $next?->large_thumbnail_url }}',
        zoom: false,
        scale: 1,
        translateX: 0,
        translateY: 0,
        thumbnailUrl: '{{ $photo->thumbnail_url }}',
        photoUrl: '{{ $photo->url }}',
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
            // Only images are preloaded with `new Image()`: handing an mp4 to
            // the image cache would waste a request, and the player already
            // fetches the video it needs.
            if (this.photoUrl && @js($photo->isImage())) {
                const image = new Image()
                image.src = this.photoUrl
            }
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
        watermarkTransparency: {{ $photo->gallery->team->brand_watermark_opacity }},
        watermarkType: @js($photo->gallery->team->brand_watermark_type),
        watermarkFontFamily: @js($watermarkFontFamily),
        watermarkFontSize: @js($watermarkLightboxFontSize),
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                navigator.userAgent,
            )
        },
        preloadAdjacentImages() {
            if (this.isMobile()) {
                if (this.previousThumbnailUrl) {
                    const img = new Image()
                    img.src = this.previousThumbnailUrl
                }
                if (this.nextThumbnailUrl) {
                    const img = new Image()
                    img.src = this.nextThumbnailUrl
                }
            } else {
                if (this.previousLargeThumbnailUrl) {
                    const img = new Image()
                    img.src = this.previousLargeThumbnailUrl
                }
                if (this.nextLargeThumbnailUrl) {
                    const img = new Image()
                    img.src = this.nextLargeThumbnailUrl
                }
            }
            // Preload the full photo URL. Videos are excluded on purpose: the
            // player fetches its own bytes, and an mp4 in the image cache is a
            // wasted request.
            if (this.photoUrl && @js($photo->isImage())) {
                const img = new Image()
                img.src = this.photoUrl
            }
        },
        imgWidth: 0,
        imgHeight: 0,
        containerWidth: 0,
        containerHeight: 0,
        watermarkStyle: '',
        repeatedWatermarkStyle: '',
        showWatermark: false,
        watermarkWidth: 0,
        watermarkHeight: 0,
        updateDimensions() {
            const source = this.$refs.photoImg
            // Videos replaced the <img> in the zoom layer, so the intrinsic
            // size comes from `videoWidth`/`videoHeight`. Until metadata
            // arrives both are 0 and the watermark simply waits.
            const video = this.$el.querySelector('#photo video')
            const img = source && source.naturalWidth > 0 ? source : video
            const container = img ? img.parentElement : null
            if (img && container) {
                // Use naturalWidth/naturalHeight for aspect ratio
                const naturalWidth = img.naturalWidth || img.videoWidth || 0
                const naturalHeight = img.naturalHeight || img.videoHeight || 0

                if (! naturalWidth || ! naturalHeight) {
                    return
                }
                const containerWidth = container.offsetWidth
                const containerHeight = container.offsetHeight
                this.containerWidth = containerWidth
                this.containerHeight = containerHeight
                let renderedWidth, renderedHeight
                const imgAspect = naturalWidth / naturalHeight
                const containerAspect = containerWidth / containerHeight
                if (imgAspect > containerAspect) {
                    renderedWidth = containerWidth
                    renderedHeight = containerWidth / imgAspect
                } else {
                    renderedHeight = containerHeight
                    renderedWidth = containerHeight * imgAspect
                }
                // Watermark position logic
                const pos = '{{ $photo->gallery->team->brand_watermark_position }}'
                const textStyle = this.watermarkType === 'text'
                    ? ` text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8), 0 0 1px rgba(0, 0, 0, 0.9); font-family: ${this.watermarkFontFamily}; font-size: ${this.watermarkFontSize};`
                    : ''
                let style = ''
                if (pos === 'top') {
                    style = `left: 50%; top: 0; transform: translateX(-50%); opacity: ${this.watermarkTransparency}; max-width: ${renderedWidth}px; max-height: ${renderedHeight}px; width: auto; height: auto;${textStyle}`
                } else if (pos === 'bottom') {
                    style = `left: 50%; bottom: 0; transform: translateX(-50%); opacity: ${this.watermarkTransparency}; max-width: ${renderedWidth}px; max-height: ${renderedHeight}px; width: auto; height: auto;${textStyle}`
                } else if (pos === 'middle') {
                    style = `left: 50%; top: 50%; transform: translate(-50%, -50%); opacity: ${this.watermarkTransparency}; max-width: ${renderedWidth}px; max-height: ${renderedHeight}px; width: auto; height: auto;${textStyle}`
                } else if (pos === 'repeated') {
                    // Tile the watermark over the rendered image bounds.
                    const url = '{{ $photo->gallery->team->brand_watermark_url }}'
                    const background = this.watermarkType === 'text'
                        ? ''
                        : `background-image: url('${url}'); background-repeat: repeat;`
                    this.repeatedWatermarkStyle = `
                                left: ${(containerWidth - renderedWidth) / 2}px;
                                top: ${(containerHeight - renderedHeight) / 2}px;
                                width: ${renderedWidth}px;
                                height: ${renderedHeight}px;
                                max-width: ${renderedWidth}px;
                                max-height: ${renderedHeight}px;
                                ${background}
                                opacity: ${this.watermarkTransparency};
                                pointer-events: none;
                                position: absolute;
                            `
                }
                this.watermarkStyle = style
                this.showWatermark = true
            }
        },
    }"
    x-init="initPhotoViewer(); updateDimensions()"
    x-on:selection-limit-reached.window="$tsui.open.modal('selection-limit')"
    x-on:video-metadata.window="updateDimensions()"
    x-on:modal-close.document="if ($event.detail.name === 'add-comment') { $wire.cancelPendingPin(); $wire.cancelPendingTime() }"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    {{-- Favourites only exist when the gallery is selectable. The guard lives
         inside the expression on purpose: a Blade `@if` around the attribute
         makes the component emit block markers inside the tag, which breaks
         parsing of every attribute that follows. --}}
    x-on:keyup.window.f="if (@js($this->photo->gallery->is_share_selectable)) $wire.favorite()"
    class="flex h-screen flex-col"
    x-on:resize.window="updateDimensions()"
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
        <div class="relative flex w-full items-center justify-center overflow-hidden">
            @if ($photo->isImage())
                <img
                    wire:ignore
                    src="{{ $photo->thumbnail_url }}"
                    srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                    sizes="(max-width: 640px) 100vw, 80vw"
                    style="background-image: url('{{ $photo->small_thumbnail_url }}'); background-size: cover; background-repeat: no-repeat; background-position: center;"
                    x-data="{ loaded: false, errored: false }"
                    x-init="
                        if ($el.complete) {
                            loaded = true
                            $el.style.backgroundImage = 'none'
                            updateDimensions()
                            preloadAdjacentImages()
                        }
                    "
                    x-show="!zoom"
                    x-ref="photoImg"
                    x-on:load="
                        loaded = true
                        $el.style.backgroundImage = 'none'
                        updateDimensions()
                        preloadAdjacentImages()
                    "
                    x-on:error="errored = true"
                    @click="if (!isMobile()) toggleZoom()"
                    @contextmenu.prevent
                    :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60'"
                    class="mx-auto h-full w-full max-w-full object-contain hover:cursor-zoom-in"
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
                    @contextmenu.prevent
                    :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60'"
                    class="mx-auto max-h-full max-w-full object-contain hover:cursor-zoom-out"
                    alt="{{ $photo->name }}"
                    x-cloak
                    data-full-image
                />
                @if ($photo->gallery->are_comments_enabled)
                    <x-photo-annotations
                        :photo="$photo"
                        :comments="$this->comments"
                        :can-annotate="$photo->gallery->are_annotations_enabled"
                    />
                @endif
            @elseif ($photo->isVideo())
                <x-video-player
                    :$photo
                    :comments="$this->comments"
                    :markers="$this->videoMarkers"
                    :start-at="$this->seekToSeconds"
                />
            @elseif ($photo->isPdf())
                <div class="flex h-full w-full flex-col items-center justify-center gap-4 px-8 text-center">
                    <x-icon name="document-text" class="size-16 text-zinc-400 dark:text-white/50" />
                    <x-ts-heading size="lg" class="max-w-full truncate">{{ $photo->name }}</x-ts-heading>
                    <x-ts-text>{{ $photo->formattedSize() }}</x-ts-text>
                    <x-ts-button
                        :href="route('shares.photos.inline', ['gallery' => $photo->gallery, 'photo' => $photo])"
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
            @if (! $photo->isPdf() && $photo->gallery->is_share_watermarked && $photo->gallery->team->hasBrandWatermark())
                @if ($photo->gallery->team->brand_watermark_position === 'repeated')
                    @if ($photo->gallery->team->isTextWatermark())
                        @php
                            $watermarkPatternId = 'watermark-'.str()->uuid();
                            $watermarkPatternHeight = max(70, (int) round($watermarkRepeatedFontPx * 3.5));
                            $watermarkPatternWidth = min(900, max(240, (int) round(mb_strlen($photo->gallery->team->brand_watermark_text ?? '') * $watermarkRepeatedFontPx * 0.65)));
                            $watermarkPatternTextY = (int) round($watermarkPatternHeight * 0.6);
                        @endphp
                        <svg
                            x-show="showWatermark"
                            :style="repeatedWatermarkStyle"
                            class="pointer-events-none absolute"
                            aria-hidden="true"
                        >
                            <defs>
                                <pattern id="{{ $watermarkPatternId }}" width="{{ $watermarkPatternWidth }}" height="{{ $watermarkPatternHeight }}" patternUnits="userSpaceOnUse" patternTransform="rotate(-20)">
                                    <text x="10" y="{{ $watermarkPatternTextY }}" fill="white" font-size="{{ $watermarkRepeatedFontPx }}" font-family="{{ $watermarkFontFamily }}" font-weight="600" style="paint-order: stroke; stroke: rgba(0, 0, 0, 0.45); stroke-width: 2px;">{{ $photo->gallery->team->brand_watermark_text }}</text>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#{{ $watermarkPatternId }})" />
                        </svg>
                    @else
                        <div
                            x-show="showWatermark"
                            :style="repeatedWatermarkStyle"
                            class="pointer-events-none absolute"
                        ></div>
                    @endif
                @elseif ($photo->gallery->team->isTextWatermark())
                    <span
                        x-show="showWatermark"
                        :style="watermarkStyle"
                        class="pointer-events-none absolute text-center font-semibold text-white select-none"
                    >{{ $photo->gallery->team->brand_watermark_text }}</span>
                @else
                    <img
                        x-show="showWatermark"
                        :style="watermarkStyle"
                        class="pointer-events-none absolute"
                        x-ref="watermarkImg"
                        @load="watermarkWidth = $event.target.naturalWidth; watermarkHeight = $event.target.naturalHeight"
                        src="{{ $photo->gallery->team->brand_watermark_url }}"
                        alt=""
                        :width="Math.min(watermarkWidth, containerWidth)"
                        :height="Math.min(watermarkHeight, containerHeight)"
                    />
                @endif
            @endif
        </div>
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
                variant="secondary"
                data-photo-back
                wire:navigate.hover
                :tooltip="__('Back to gallery :name', ['name' => $photo->gallery->name])"
                :aria-label="__('Back to gallery :name', ['name' => $photo->gallery->name])"
            >
                {{ __('Back') }}
            </x-ts-button>

        </div>

        {{-- Bottom dock: the same icon-only controls as the owner viewer,
             minus everything the client is not allowed to do. --}}
        <x-photo-dock
            x-show="! zoom"
            style="display: none"
            data-viewer-dock
        >
            {{-- Navigation first: previous / next live in the dock now. --}}
            @if ($previous)
                <x-dock-button
                    :href="route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $previous, 'navigateCommented' => $navigateCommented ? true : null, 'navigateFavorites' => $navigateFavorites ? true : null])"
                    icon="chevron-left"
                    :label="__('Previous photo')"
                    hint="←"
                    x-ref="previous"
                    wire:navigate.hover
                />
            @endif

            @if ($next)
                <x-dock-button
                    :href="route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $next, 'navigateCommented' => $navigateCommented ? true : null, 'navigateFavorites' => $navigateFavorites ? true : null])"
                    icon="chevron-right"
                    :label="__('Next photo')"
                    hint="→"
                    x-ref="next"
                    wire:navigate.hover
                />
            @endif

            <x-dock-divider />

            @if ($this->photo->gallery->is_share_selectable)
                <x-dock-button
                    icon="heart"
                    :label="__('Favorite')"
                    hint="F"
                    :tone="$photo->isFavorited() ? 'favorite' : 'default'"
                    data-dock-favorite
                    wire:click="favorite"
                />
            @endif

            @if ($photo->gallery->are_comments_enabled)
                <x-ts-modal-trigger name="add-comment">
                    {{-- On a video the playhead is the anchor: pause and
                         capture the current second before the composer opens.
                         The listener is always bound (the player simply ignores
                         it when absent) because a Blade conditional inside a
                         component tag stops Blade from compiling the tag. --}}
                    <x-dock-button
                        icon="chat-bubble-left-ellipsis"
                        :label="__('Comments (:count)', ['count' => $this->comments->count()])"
                        :tone="$this->comments->isEmpty() ? 'default' : 'ink'"
                        data-photo-comments
                        x-on:click="$dispatch('video-capture-time')"
                    />
                </x-ts-modal-trigger>

                <x-photo-annotate-button
                    :can-annotate="$photo->isImage() && $photo->gallery->are_annotations_enabled"
                    :has-annotations="$this->annotationsCount > 0"
                />
            @endif

            @if ($photo->gallery->are_comments_enabled && $photo->gallery->are_annotations_enabled && $this->annotationsCount > 0)
                <x-photo-annotations-toggle />
            @endif

            @php
                $showsVersions = config('picstome.file_versions_enabled')
                    && $photo->gallery->is_share_versions_visible;
                $showsDownload = $this->photo->gallery->is_share_downloadable;
            @endphp

            @if ($showsVersions || $showsDownload)
                <x-dock-divider />
            @endif

            @if ($showsVersions)
                <livewire:photo-versions :photo="$photo" :manageable="false" />
            @endif

            @if ($showsDownload)
                @if ($photo->path && $photo->raw_path)
                    <x-dropdown position="top-end">
                        <x-slot:action>
                            <x-dock-button icon="cloud-arrow-down" :label="__('Download')" tone="accent" x-on:click="show = ! show" />
                        </x-slot:action>

                        <x-dropdown.items
                            :text="__('Download JPG')"
                            icon="cloud-arrow-down"
                            :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'jpg'])"
                        />
                        <x-dropdown.items
                            :text="__('Download Raw')"
                            icon="cloud-arrow-down"
                            :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'raw'])"
                        />
                    </x-dropdown>
                @else
                    <x-dock-button
                        icon="cloud-arrow-down"
                        :label="__('Download')"
                        :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                        tone="accent"
                    />
                @endif
            @endif
        </x-photo-dock>
    </div>
    @if ($photo->gallery->are_comments_enabled)
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
                                :can-delete="$this->canManageComments"
                                :align-right="$comment->user && $comment->user->is($photo->gallery->team->owner)"
                                :current-version-number="$this->currentVersionNumber"
                                :version-numbers="$this->versionNumbers"
                            />
                        @endforeach
                    </div>
                @endif

                <form wire:submit="addComment" class="space-y-4 pt-2">
                    @if ($photo->isVideo() && $pendingTimeSeconds !== null)
                        <div class="flex items-center gap-2 rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800">
                            <x-icon name="clock" class="size-4 shrink-0 text-zinc-500" />
                            <span>{{ __('Comment at :time', ['time' => \App\Models\PhotoComment::formatTimecode($pendingTimeSeconds)]) }}</span>
                            <x-ts-spacer />
                            <x-ts-button
                                variant="subtle"
                                size="xs"
                                square
                                icon="x-mark"
                                wire:click="cancelPendingTime"
                                :aria-label="__('Remove timestamp')"
                                data-clear-comment-time
                            />
                        </div>
                    @endif

                    <x-ts-textarea wire:model.defer="commentText" :label="__('Add a comment')" rows="3" />

                    <div class="flex">
                        <x-ts-spacer />
                        <x-ts-button type="submit" variant="primary">{{ __('Submit') }}</x-ts-button>
                    </div>
                </form>
            </div>
        </x-ts-modal>
    @endif

    @if ($photo->isVideo())
        @script
            <script>
                @include('partials.video-player-script')
            </script>
        @endscript
    @endif

    @unlesssubscribed($photo->gallery->team)
        <div class="py-3">
            @include('partials.powered-by')
        </div>
    @endsubscribed

    <x-confirm-modal
        name="delete-comment"
        :title="__('Delete comment?')"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingCommentId ? 'deleteComment('.$this->pendingCommentId.')' : null"
    />

    <x-selection-limit-modal :limit="$photo->gallery->share_selection_limit" />
</div>

