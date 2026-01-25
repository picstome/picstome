<?php

use App\Models\Photo;
use App\Models\PhotoComment;
use App\Notifications\GuestPhotoCommented;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.guest', ['fullScreen' => true])] class extends Component
{
    public Photo $photo;

    public ?Photo $next;

    public ?Photo $previous;

    #[Url]
    public $navigateFavorites = false;

    #[Url]
    public $navigateCommented = false;

    public $commentText = '';

    public function mount(Photo $photo)
    {
        abort_unless($photo->gallery->is_shared, 404);

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
        abort_unless($this->photo->gallery->is_share_selectable, 401);

        if ($this->photo->gallery->photos()->favorited()->count() === $this->photo->gallery->share_selection_limit) {
            $this->dispatch('selection-limit-reached');
        }

        $this->photo->toggleFavorite();
    }

    #[Computed]
    public function galleryUrl()
    {
        return Str::of(route('shares.show', ['gallery' => $this->photo->gallery, 'slug' => $this->photo->gallery->slug]))
            ->when($this->navigateFavorites, fn ($str) => $str->append('?activeTab=favorited'))
            ->when($this->navigateCommented, fn ($str) => $str->append('?activeTab=commented'))
            ->append('#')
            ->append($this->navigateFavorites ? 'favorite-' : ($this->navigateCommented ? 'commented-' : 'photo-'))
            ->append($this->photo->id);
    }

    public function addComment()
    {
        abort_unless($this->photo->gallery->are_comments_enabled, 403);

        $this->validate([
            'commentText' => 'required|string|max:1000',
        ]);

        abort_if(Auth::check() && ! $this->photo->gallery->team->owner->is(Auth::user()), 403);

        $comment = $this->photo->comments()->create([
            'user_id' => Auth::id() ?: null,
            'comment' => $this->commentText,
        ]);

        if (Auth::guest()) {
            $this->photo
                ->gallery
                ->team->owner->notify(new GuestPhotoCommented($this->photo, $comment));
        }

        $this->commentText = '';
    }

    public function deleteComment(PhotoComment $comment)
    {
        abort_unless($this->photo->gallery->are_comments_enabled, 403);

        abort_unless($comment->photo->is($this->photo), 404);

        abort_unless(Auth::check() && $this->photo->gallery->team->owner->is(Auth::user()), 403);

        $comment->delete();
    }

    #[Computed]
    public function comments()
    {
        return $this->photo->comments()->latest()->with('user')->get();
    }
}; ?>

<div
    x-data="{
        previousThumbnailUrl: '{{ $previous?->thumbnail_url }}',
        nextThumbnailUrl: '{{ $next?->thumbnail_url }}',
        previousLargeThumbnailUrl: '{{ $previous?->large_thumbnail_url }}',
        nextLargeThumbnailUrl: '{{ $next?->large_thumbnail_url }}',
        swipe: '',
        zoom: false,
        pinchZooming: false,
        thumbnailUrl: '{{ $photo->thumbnail_url }}',
        photoUrl: '{{ $photo->url }}',
        navigating: false,
        watermarkTransparency:
            {{ $photo->gallery->team->brand_watermark_transparency ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100 : 1 }},
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
            // Preload full photo URL
            if (this.photoUrl) {
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
            const img = this.$refs.photoImg
            const container = img ? img.parentElement : null
            if (img && container) {
                // Use naturalWidth/naturalHeight for aspect ratio
                const naturalWidth = img.naturalWidth
                const naturalHeight = img.naturalHeight
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
                let style = ''
                if (pos === 'top') {
                    style = `left: 50%; top: 0; transform: translateX(-50%); opacity: ${this.watermarkTransparency}; max-width: ${renderedWidth}px; max-height: ${renderedHeight}px; width: auto; height: auto;`
                } else if (pos === 'bottom') {
                    style = `left: 50%; bottom: 0; transform: translateX(-50%); opacity: ${this.watermarkTransparency}; max-width: ${renderedWidth}px; max-height: ${renderedHeight}px; width: auto; height: auto;`
                } else if (pos === 'middle') {
                    style = `left: 50%; top: 50%; transform: translate(-50%, -50%); opacity: ${this.watermarkTransparency}; max-width: ${renderedWidth}px; max-height: ${renderedHeight}px; width: auto; height: auto;`
                } else if (pos === 'repeated') {
                    // Tile watermark as background
                    const url = '{{ $photo->gallery->team->brand_watermark_url }}'
                    this.repeatedWatermarkStyle = `
                            left: ${(containerWidth - renderedWidth) / 2}px;
                            top: ${(containerHeight - renderedHeight) / 2}px;
                            width: ${renderedWidth}px;
                            height: ${renderedHeight}px;
                            max-width: ${renderedWidth}px;
                            max-height: ${renderedHeight}px;
                            background-image: url('${url}');
                            background-repeat: repeat;
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
    x-init="
        (() => {
            const hammer = new Hammer($el, { touchAction: 'auto' })
            hammer.get('pinch').set({ enable: true })
            hammer.on('pinchstart panleft panright', function (ev) {
                $dispatch(ev.type, ev)
            })
            updateDimensions()
        })()
    "
    x-on:selection-limit-reached.window="alert('{{ __('You have reached the limit for photo selection.') }}')"
    @keyup.window.left="$refs.previous && Livewire.navigate($refs.previous.href)"
    @keyup.window.right="$refs.next && Livewire.navigate($refs.next.href)"
    @panleft="if (!navigating && $refs.next) { navigating = true; Livewire.navigate($refs.next.href); setTimeout(() => { navigating = false }, 500) }"
    @panright="if (!navigating && $refs.previous) { navigating = true; Livewire.navigate($refs.previous.href); setTimeout(() => { navigating = false }, 500) }"
    @pinchstart="pinchZooming = true;"
    @if ($this->photo->gallery->is_share_selectable)
        @keyup.window.f
        ="$wire.favorite()"
    @endif
    class="flex h-screen flex-col"
    x-on:resize.window="updateDimensions()"
>
    <div id="photo" class="relative h-full flex-1" :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'">
        <div
            class="relative flex w-full items-center justify-center"
            :class="zoom ? 'overflow-scroll' : 'overflow-hidden flex'"
        >
            @if ($photo->isImage())
                <img
                    src="{{ $photo->thumbnail_url }}"
                    srcset="{{ $photo->thumbnail_url }} 1000w, {{ $photo->large_thumbnail_url }} 2040w"
                    sizes="(max-width: 640px) 100vw, 80vw"
                    x-data="{ loaded: false, errored: false }"
                    x-init="
                        if ($el.complete) {
                            loaded = true
                            updateDimensions()
                            preloadAdjacentImages()
                        }
                    "
                    x-show="!zoom && !pinchZooming"
                    x-ref="photoImg"
                    x-on:load="
                        loaded = true
                        updateDimensions()
                        preloadAdjacentImages()
                    "
                    x-on:error="errored = true"
                    @click="if (!isMobile()) zoom = true"
                    @contextmenu.prevent
                    :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60'"
                    class="mx-auto h-full w-full max-w-full object-contain hover:cursor-zoom-in"
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
                    :class="loaded || errored ? '' : 'animate-pulse bg-black/60 dark:bg-white/60'"
                    class="mx-auto h-full w-full max-w-full object-contain"
                    alt="{{ $photo->name }}"
                    loading="lazy"
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
                    @contextmenu.prevent
                    class="mx-auto max-w-none object-contain hover:cursor-zoom-out"
                    loading="lazy"
                    alt="{{ $photo->name }}"
                    x-cloak
                />
            @elseif ($photo->isVideo())
                <video
                    x-data="{
                        loaded: false,
                        errored: false,
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
                    x-ref="photoImg"
                    x-on:loadeddata="
                        loaded = true
                        updateDimensions()
                        preloadAdjacentImages()
                    "
                    x-on:error="errored = true"
                >
                    <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                    Your browser does not support the video tag.
                </video>
            @else
                <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
            @endif
            @if ($photo->gallery->is_share_watermarked && $photo->gallery->team->brand_watermark_url)
                @if ($photo->gallery->team->brand_watermark_position === 'repeated')
                    <div
                        x-show="showWatermark"
                        :style="repeatedWatermarkStyle"
                        class="pointer-events-none absolute"
                    ></div>
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
        <div
            class="absolute top-0 bottom-0 left-0 flex items-center px-3 max-sm:top-auto max-sm:px-1 max-sm:py-1"
            :class="zoom ? 'hidden' : 'flex'"
        >
            @if ($previous)
                <flux:button
                    href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $previous, 'navigateCommented' => $navigateCommented ? true : null, 'navigateFavorites' => $navigateFavorites ? true : null]) }}"
                    wire:navigate.hover
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
                    href="{{ route('shares.photos.show', ['gallery' => $photo->gallery, 'photo' => $next, 'navigateCommented' => $navigateCommented ? true : null, 'navigateFavorites' => $navigateFavorites ? true : null]) }}"
                    wire:navigate.hover
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
                    wire:navigate.hover
                    icon="arrow-left"
                    size="sm"
                    icon:variant="micro"
                >
                    {{ __('Back') }}
                </flux:button>
            </div>
            <div class="flex gap-3">
                @if ($photo->gallery->are_comments_enabled)
                    <flux:modal.trigger name="add-comment">
                        <flux:button icon="chat-bubble-left-ellipsis" size="sm" class="max-sm:hidden">
                            @if ($this->comments->isEmpty())
                                {{ __('Add comment') }}
                            @else
                                {{ __('Comments (:count)', ['count' => $this->comments->count()]) }}
                            @endif
                        </flux:button>

                        <flux:button icon="chat-bubble-left-ellipsis" size="sm" class="sm:hidden" square />
                    </flux:modal.trigger>
                @endif

                @if ($this->photo->gallery->is_share_downloadable)
                    @if ($photo->path && $photo->raw_path)
                        <flux:dropdown>
                            <flux:button icon="cloud-arrow-down" icon:variant="mini" size="sm" square />
                            <flux:menu>
                                <flux:menu.item
                                    :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'jpg'])"
                                    icon="cloud-arrow-down"
                                >
                                    {{ __('Download JPG') }}
                                </flux:menu.item>
                                <flux:menu.item
                                    :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo, 'type' => 'raw'])"
                                    icon="cloud-arrow-down"
                                >
                                    {{ __('Download Raw') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <flux:button
                            :href="route('shares.photos.download', ['gallery' => $photo->gallery, 'photo' => $photo])"
                            icon="cloud-arrow-down"
                            icon:variant="mini"
                            size="sm"
                            square
                        />
                    @endif
                @endif

                @if ($this->photo->gallery->is_share_selectable)
                    <flux:button
                        name="favorite"
                        wire:click="favorite"
                        icon="heart"
                        :icon:variant="$photo->isFavorited() ? 'mini' : 'outline'"
                        size="sm"
                        @class(['text-red-500!' => $photo->isFavorited()])
                        square
                    />
                @endif
            </div>
        </div>
    </div>
    @if ($photo->gallery->are_comments_enabled)
        <flux:modal name="add-comment" class="w-full sm:max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Comments') }}</flux:heading>
                    <flux:subheading>{{ __('All comments for this photo.') }}</flux:subheading>
                </div>
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
                                    @if (auth()->check() && $photo->gallery->team->owner->is(auth()->user()))
                                        <flux:button
                                            wire:click="deleteComment({{ $comment->id }})"
                                            wire:confirm="{{ __('Are you sure?') }}"
                                            icon="x-mark"
                                            variant="subtle"
                                            size="xs"
                                            inset="right"
                                            square
                                        />
                                    @endif
                                </div>
                                <flux:text variant="strong">
                                    {{ $comment->comment }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif

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
    @endif

    @unlesssubscribed($photo->gallery->team)
        <div class="py-3">
            @include('partials.powered-by')
        </div>
    @endsubscribed
</div>

@assets
    <script type="text/javascript" src="https://unpkg.com/hammerjs@2.0.8/hammer.min.js"></script>
@endassets
