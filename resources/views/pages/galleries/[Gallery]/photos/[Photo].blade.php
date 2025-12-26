<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;

middleware(['auth', 'verified', 'can:view,photo']);

new class extends Component
{
    public Photo $photo;

    public ?Photo $next;

    public ?Photo $previous;

    public $commentText = '';

    #[Url]
    public $navigateFavorites = false;

    #[Url]
    public $navigateCommented = false;

    public function mount()
    {
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

        $this->photo->gallery->setCoverPhoto($this->photo);
    }

    public function addComment()
    {
        $this->validate([
            'commentText' => 'required|string|max:1000',
        ]);

        $this->photo->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $this->commentText,
        ]);

        $this->commentText = '';
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
}; ?>

<x-app-layout :full-screen="true">
    @volt('pages.galleries.photos.show')
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
                @else
                    <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
                @endif

                <div
                    class="absolute top-0 bottom-0 left-0 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
                    :class="zoom ? 'hidden' : 'flex'"
                >
                    @if ($previous)
                        <flux:button
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
                    class="absolute top-0 right-0 bottom-0 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
                    :class="zoom ? 'hidden' : 'flex'"
                >
                    @if ($next)
                        <flux:button
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
                    class="absolute top-0 right-0 left-0 flex items-center justify-between gap-4 p-3 max-sm:p-1"
                    :class="zoom ? 'hidden' : 'flex'"
                >
                    <div class="flex gap-3">
                        <flux:button
                            :href="$this->galleryUrl"
                            wire:navigate
                            icon="arrow-left"
                            size="sm"
                            icon:variant="micro"
                        >
                            {{ __('Back') }}
                        </flux:button>
                    </div>
                    <div class="flex gap-3">
                        <flux:tooltip toggleable>
                            <flux:button icon="information-circle" size="sm" variant="subtle" />
                            <flux:tooltip.content class="max-w-[20rem] space-y-2">
                                <p>{{ $photo->name }}</p>
                            </flux:tooltip.content>
                        </flux:tooltip>
                        <flux:dropdown>
                            <flux:button icon="ellipsis-vertical" size="sm" variant="subtle" />
                            <flux:menu>
                                @if ($photo->gallery->coverPhoto?->is($photo))
                                    <flux:menu.item wire:click="removeAsCover" icon="x-mark">
                                        {{ __('Remove as Cover') }}
                                    </flux:menu.item>
                                @else
                                    <flux:menu.item wire:click="setAsCover" icon="star">
                                        {{ __('Set as Cover') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.item
                                    wire:click="delete"
                                    wire:confirm="{{ __('Are you sure?') }}"
                                    icon="trash"
                                    variant="danger"
                                >
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        @php
                            $comments = $photo->comments()->latest()->with('user')->get();
                        @endphp

                        <flux:modal.trigger name="add-comment">
                            <flux:button icon="chat-bubble-left-ellipsis" size="sm">
                                @if ($comments->isEmpty())
                                    {{ __('Add comment') }}
                                @else
                                    {{ __('Comments (:count)', ['count' => $comments->count()]) }}
                                @endif
                            </flux:button>
                        </flux:modal.trigger>

                        @if ($photo->path && $photo->raw_path)
                            <flux:dropdown>
                                <flux:button icon="cloud-arrow-down" icon:variant="mini" size="sm" square />
                                <flux:menu>
                                    <flux:menu.item
                                        :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'jpg'])"
                                        icon="cloud-arrow-down"
                                    >
                                        {{ __('Download JPG') }}
                                    </flux:menu.item>
                                    <flux:menu.item
                                        :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'raw'])"
                                        icon="cloud-arrow-down"
                                    >
                                        {{ __('Download Raw') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @else
                            <flux:button
                                :href="route('galleries.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                                icon="cloud-arrow-down"
                                icon:variant="mini"
                                size="sm"
                                square
                            />
                        @endif
                        <flux:button
                            wire:click="favorite"
                            icon="heart"
                            :icon:variant="$photo->isFavorited() ? 'mini' : 'outline'"
                            size="sm"
                            @class(['text-red-500!' => $photo->isFavorited()])
                            square
                        />
                    </div>
                </div>
            </div>

            <flux:modal name="add-comment" class="w-full sm:max-w-lg">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Comments') }}</flux:heading>
                        <flux:subheading>{{ __('All comments for this photo.') }}</flux:subheading>
                    </div>

                    <div class="max-h-64 space-y-4 overflow-y-auto">
                        @if ($this->comments->isNotEmpty())
                            <div class="max-h-64 space-y-4 overflow-y-auto">
                                @foreach ($this->comments as $comment)
                                    <div
                                        @class([
                                        'group relative rounded bg-zinc-50 p-3 dark:bg-zinc-900',
                                        'ml-auto text-right' => $comment->user && $comment->user->is($photo->gallery->team->owner),
                                        ])
                                    >
                                        <div
                                            @class([
                                            'mb-1 flex items-center gap-2',
                                            'justify-end text-right' => $comment->user && $comment->user->is($photo->gallery->team->owner),
                                            ])
                                        >
                                            @if ($comment->user)
                                                <flux:text variant="strong" class="text-sm font-semibold">
                                                    {{ $comment->user->name }}
                                                </flux:text>
                                                <flux:text>&middot;</flux:text>
                                            @endif

                                            <flux:text class="text-xs">
                                                {{ $comment->created_at->diffForHumans() }}
                                            </flux:text>
                                            <flux:button
                                                wire:click="deleteComment({{ $comment->id }})"
                                                wire:confirm="{{ __('Are you sure?') }}"
                                                icon="x-mark"
                                                variant="subtle"
                                                size="xs"
                                                inset="right"
                                                square
                                            />
                                        </div>
                                        <flux:text variant="strong">
                                            {{ $comment->comment }}
                                        </flux:text>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <form wire:submit="addComment" class="space-y-4 pt-2">
                        <flux:textarea wire:model.defer="commentText" :label="__('Add a comment')" rows="3" />
                        <flux:error name="commentText" />
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">{{ __('Submit') }}</flux:button>
                        </div>
                    </form>
                </div>
            </flux:modal>
        </div>

        @assets
            <script type="text/javascript" src="https://unpkg.com/hammerjs@2.0.8/hammer.min.js"></script>
        @endassets

        @push('head')
            <link rel="preload" as="image" href="{{ $photo->url }}" />

            @if ($next)
                <link rel="preload" as="image" href="{{ $next->url }}" />
                <link rel="preload" as="image" href="{{ $next->thumbnail_url }}" />
                <link rel="preload" as="image" href="{{ $next->large_thumbnail_url }}" />
            @endif

            @if ($previous)
                <link rel="preload" as="image" href="{{ $previous->url }}" />
                <link rel="preload" as="image" href="{{ $previous->thumbnail_url }}" />
                <link rel="preload" as="image" href="{{ $previous->large_thumbnail_url }}" />
            @endif
        @endpush
    @endvolt
</x-app-layout>
