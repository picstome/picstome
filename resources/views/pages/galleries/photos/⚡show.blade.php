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
        swipe: '',
        zoom: false,
        pinchZooming: false,
        navigating: false,
        isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                navigator.userAgent,
            )
        },
    }"
    x-init="
        (() => {
            const hammer = new Hammer($el, { touchAction: 'auto' })
            hammer.get('pinch').set({ enable: true })
            hammer.on('pinch panleft panright', function (ev) {
                $dispatch(ev.type, ev)
            })
        })()
    "
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    @keyup.window.f="$wire.favorite()"
    @panleft="if (!navigating && $refs.next) { navigating = true; Livewire.navigate($refs.next.href); setTimeout(() => { navigating = false }, 500) }"
    @panright="if (!navigating && $refs.previous) { navigating = true; Livewire.navigate($refs.previous.href); setTimeout(() => { navigating = false }, 500) }"
    @pinchstart="pinchZooming = true;"
    x-on:modal-close.document="if ($event.detail.name === 'add-comment') $wire.cancelPendingPin()"
    class="flex h-screen flex-col"
>
    <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
        @if ($photo->isImage())
            <img
                src="{{ $photo->thumbnail_url }}"
                srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                sizes="(max-width: 640px) 100vw, 80vw"
                x-data="{ loaded: false, errored: false }"
                x-init="if ($el.complete) loaded = true"
                x-show="!zoom && !pinchZooming"
                x-on:load="loaded = true"
                x-on:error="errored = true"
                @click="if (!isMobile()) zoom = true"
                :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60 h-full w-full'"
                class="mx-auto max-w-full object-contain hover:cursor-zoom-in"
                alt="{{ $photo->name }}"
            />

            <img
                src="{{ $photo->url }}"
                x-data="{ loaded: false, errored: false }"
                x-init="if ($el.complete) loaded = true"
                x-show="!zoom && pinchZooming"
                x-on:load="loaded = true"
                x-on:error="errored = true"
                @click="if (!isMobile()) zoom = true"
                class="mx-auto max-w-full object-contain hover:cursor-zoom-in"
                :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60 h-full w-full'"
                loading="lazy"
                alt="{{ $photo->name }}"
                x-cloak
            />

            <img
                src="{{ $photo->url }}"
                x-data="{ loaded: false, errored: false }"
                x-init="if ($el.complete) loaded = true"
                x-show="zoom"
                x-on:load="loaded = true"
                x-on:error="errored = true"
                @click="zoom = false"
                class="mx-auto max-w-none object-contain hover:cursor-zoom-out"
                :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60 h-full w-full'"
                loading="lazy"
                alt="{{ $photo->name }}"
                x-cloak
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
    <script type="text/javascript" src="https://unpkg.com/hammerjs@2.0.8/hammer.min.js"></script>
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
