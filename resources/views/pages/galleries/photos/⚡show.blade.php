<?php

use App\Traits\InteractsWithTallStackModals;
use App\Models\Photo;
use App\Models\PhotoComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app', ['fullScreen' => true])] class extends Component
{
    use InteractsWithTallStackModals;
    public Photo $photo;

    public ?Photo $next;

    public ?Photo $previous;

    public $commentText = '';

    public ?float $pendingPinX = null;

    public ?float $pendingPinY = null;

    #[Url]
    public $navigateFavorites = false;

    #[Url]
    public $navigateCommented = false;

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

    public function delete()
    {
        $gallery = $this->photo->gallery;

        $this->photo->deleteFromDisk()->delete();

        return $this->redirect("/galleries/{$gallery->id}");
    }

    public function setAsCover()
    {
        $this->authorize('updateCover', $this->photo->gallery);

        abort_unless($this->photo->isImage(), 422);

        $this->photo->gallery->setCoverPhoto($this->photo);
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

    #[Computed]
    public function galleryUrl()
    {
        return Str::of(route('galleries.show', ['gallery' => $this->photo->gallery]))
            ->when($this->navigateFavorites, fn ($str) => $str->append('?activeTab=favorited'))
            ->when($this->navigateCommented, fn ($str) => $str->append('?activeTab=commented'))
            ->append('#')
            ->append($this->navigateFavorites ? 'favorite-' : ($this->navigateCommented ? 'commented-' : 'photo-'))
            ->append($this->photo->id);
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
    }"
    x-init="initPhotoViewer()"
    data-full-image-url="{{ $photo->url }}"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    @keyup.window.f="$wire.favorite()"
    x-on:modal-close.document="if ($event.detail.name === 'add-comment') $wire.cancelPendingPin()"
    class="flex h-screen flex-col"
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

        <div
            class="absolute top-0 bottom-0 left-0 z-30 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
            :class="zoom ? 'hidden' : 'flex'"
        >
            @if ($previous)
                <x-ts-button
                    href="/galleries/{{ $photo->gallery->id }}/photos/{{ $previous->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : ($navigateCommented ? '?navigateCommented=true' : '') }}"
                    wire:navigate
                    x-ref="previous"
                    icon="chevron-left"
                    size="sm"
                    class="py-10 max-sm:py-0"
                    square
                />
            @endif
        </div>
        <div
            class="absolute top-0 right-0 bottom-0 z-30 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
            :class="zoom ? 'hidden' : 'flex'"
        >
            @if ($next)
                <x-ts-button
                    href="/galleries/{{ $photo->gallery->id }}/photos/{{ $next->id }}{{ $navigateFavorites ? '?navigateFavorites=true' : ($navigateCommented ? '?navigateCommented=true' : '') }}"
                    wire:navigate
                    x-ref="next"
                    icon="chevron-right"
                    size="sm"
                    class="py-10 max-sm:py-0"
                    square
                />
            @endif
        </div>
        <div
            class="absolute top-0 right-0 left-0 z-30 flex items-center justify-between gap-4 p-3 max-sm:p-1"
            :class="zoom ? 'hidden' : 'flex'"
        >
            <div class="flex gap-3">
                <x-ts-button
                    :href="$this->galleryUrl"
                    wire:navigate
                    icon="arrow-left"
                    size="sm"
                    icon:variant="micro"
                    variant="primary"
                    class="btn-ink"
                >
                    {{ __('Back') }}
                </x-ts-button>
            </div>
            <div
                class="flex gap-3"
                x-data="{ annotating: false }"
                x-on:annotations-toggled.window="annotating = $event.detail.active"
            >
                <span x-data x-tooltip="{{ $photo->name }}">
                    <x-ts-button icon="information-circle" size="sm" variant="subtle" />
                </span>
                <x-dropdown position="bottom-end">
                    <x-slot:action>
                        <x-ts-button icon="ellipsis-vertical" size="sm" variant="subtle" x-on:click="show = ! show" />
                    </x-slot:action>

                    @if ($photo->isImage())
                        @if ($photo->gallery->coverPhoto?->is($photo))
                            <x-dropdown.items :text="__('Remove as Cover')" icon="x-mark" wire:click="removeAsCover" />
                        @else
                            <x-dropdown.items :text="__('Set as Cover')" icon="star" wire:click="setAsCover" />
                        @endif
                    @endif

                    <x-dropdown.items
                        :text="__('Delete')"
                        icon="trash"
                        x-on:click="show = false; $tsui.open.modal('delete-photo')"
                        class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                    />
                </x-dropdown>

                <div class="contents" x-show="! annotating">
                    <x-ts-modal-trigger name="add-comment">
                        <x-ts-button icon="chat-bubble-left-ellipsis" size="sm" class="max-sm:hidden" variant="primary">
                            @if ($this->comments->isEmpty())
                                {{ __('Add comment') }}
                            @else
                                {{ __('Comments (:count)', ['count' => $this->comments->count()]) }}
                            @endif
                        </x-ts-button>

                        <x-ts-button icon="chat-bubble-left-ellipsis" size="sm" class="sm:hidden" variant="primary" square />
                    </x-ts-modal-trigger>
                </div>

                <x-photo-annotate-button :can-annotate="$photo->isImage()" />

                @if ($photo->path && $photo->raw_path)
                    <x-dropdown position="bottom-end">
                        <x-slot:action>
                            <x-ts-button icon="cloud-arrow-down" size="sm" variant="primary" class="btn-ink" square x-on:click="show = ! show" />
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
                    <x-ts-button
                        :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                        icon="cloud-arrow-down"
                        icon:variant="mini"
                        size="sm"
                        variant="primary"
                        class="btn-ink"
                        square
                    />
                @endif
                <x-ts-button
                    wire:click="favorite"
                    icon="heart"
                    :icon:variant="$photo->isFavorited() ? 'mini' : 'outline'"
                    :variant="$photo->isFavorited() ? 'primary' : 'outline'"
                    size="sm"
                    @class(['btn-coral' => $photo->isFavorited()])
                    square
                />
            </div>
        </div>

        <div
            x-data="{ annotating: false }"
            x-on:annotations-toggled.window="annotating = $event.detail.active"
            x-show="! annotating && ! zoom"
            style="display: none"
            class="absolute right-3 bottom-3 z-10 flex items-center gap-2"
        >
            @if (config('picstome.file_versions_enabled'))
                <livewire:photo-versions :photo="$photo" :manageable="true" />
            @endif

            @if ($this->annotationsCount > 0)
                <x-photo-annotations-toggle />
            @endif
        </div>
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
</div>




@assets
@endassets

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
