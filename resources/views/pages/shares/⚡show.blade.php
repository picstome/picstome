<?php

use App\Models\Gallery;
use App\Traits\SummarizesGalleryTabs;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use SummarizesGalleryTabs;

    public Gallery $gallery;

    public Collection $favorites;

    public Collection $commentedPhotos;

    #[Url]
    public $activeTab = 'all';

    public function mount(Gallery $gallery, string $slug)
    {
        abort_unless($gallery->is_shared, 404);
        abort_if($gallery->slug !== $slug, 404);
        $this->getFavorites();
        $this->getCommentedPhotos();
    }

    #[On('photo-favorited')]
    public function getFavorites()
    {
        $this->favorites = $this->gallery->photosInOrder($this->gallery->favoritePhotoIds());
    }

    public function getCommentedPhotos()
    {
        $this->commentedPhotos = $this->gallery->photosInOrder($this->gallery->commentedPhotoIds());
    }

    #[Computed]
    public function coverImage()
    {
        if ($this->gallery->coverPhoto && $this->gallery->coverPhoto->isImage()) {
            return $this->gallery->coverPhoto;
        }

        return $this->gallery->firstImage();
    }

    #[Computed]
    public function allPhotos()
    {
        return $this->gallery->photosInOrder($this->gallery->photoIds());
    }
};
?>

<x-slot name="font">{{ $gallery->team->brand_font }}</x-slot>

<x-slot name="color">{{ $gallery->team->brand_color }}</x-slot>

<div x-data x-on:selection-limit-reached.window="$tsui.open.modal('selection-limit')" class="h-full">
    @if ($this->allPhotos->isNotEmpty())
        <div class="relative">
            <a href="{{ route('handle.show', ['handle' => $gallery->team->handle]) }}">
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </a>
        </div>
        @if ($gallery->coverPhoto)
            <div class="relative mt-4 h-[328px] overflow-hidden md:h-[480px] lg:mt-8">
                <img src="{{ $this->coverImage?->cover_image_url }}" class="h-full w-full object-cover" />
            </div>
        @endif
    @else
        <div>
            <a href="{{ route('handle.show', ['handle' => $gallery->team->handle]) }}">
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </a>
        </div>
    @endif
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <div class="flex items-center gap-4">
                <x-ts-heading level="1" size="xl">{{ $gallery->name }}</x-ts-heading>
            </div>

            <div class="mt-3 h-1 w-10 rounded-full bg-(--color-accent)"></div>

            @if ($gallery->share_description)
                <x-ts-subheading class="mt-2">
                    {{ $gallery->share_description }}
                </x-ts-subheading>
            @endif
        </div>
    </div>
    @if ($this->allPhotos->isNotEmpty())
        <div class="mt-8">
            <x-gallery-tabs
                :favorites-count="$this->favorites->count()"
                :selection-limit="$gallery->share_selection_limit"
                :comments-count="$this->totalComments()"
                :show-favorites="$gallery->is_share_selectable"
                :show-comments="$gallery->are_comments_enabled"
                :download-url="$gallery->is_share_downloadable ? route('shares.download', ['gallery' => $gallery]) : null"
            />
            <div x-show="$wire.activeTab === 'all'" class="pt-1">
                <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($this->allPhotos as $photo)
                        <livewire:shared-photo-item :$photo :key="'photo-'.$photo->id" :html-id="'photo-'.$photo->id" />
                    @endforeach
                </div>
            </div>
            <div x-show="$wire.activeTab === 'commented'" class="pt-1">
                <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($this->commentedPhotos as $photo)
                        <livewire:shared-photo-item
                            :$photo
                            :asCommented="true"
                            :key="'commented-'.$photo->id"
                            :html-id="'commented-'.$photo->id"
                        />
                    @endforeach
                </div>
            </div>
            <div x-show="$wire.activeTab === 'favorited'" class="pt-1">
                @if ($favorites->isNotEmpty())
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($this->favorites as $photo)
                            <livewire:shared-photo-item
                                :$photo
                                :asFavorite="true"
                                :key="'favorite-'.$photo->id"
                                :html-id="'favorite-'.$photo->id"
                            />
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-14">
                        <x-ts-text class="max-w-72 text-center">
                            {{ __('No favorites yet. Click on the heart icon to select your favorites.') }}
                        </x-ts-text>
                    </div>
                @endif
            </div>
            @if ($gallery->is_share_downloadable)
                <div x-show="$wire.activeTab === 'download'" class="pt-1">
                    <x-gallery-download-panel
                        :count="$this->allPhotos->count()"
                        :size="$this->formattedDownloadSize()"
                        :href="route('shares.download', ['gallery' => $gallery])"
                    />
                </div>
            @endif
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No photos') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __("We couldn't find any photos.") }}
            </x-ts-subheading>
        </div>
    @endif
    @unlesssubscribed($gallery->team)
        <div class="mt-10">
            @include('partials.powered-by')
        </div>
    @endsubscribed

    <x-selection-limit-modal :limit="$gallery->share_selection_limit" />
</div>
@script
    <script>
        document.addEventListener('livewire:navigated', () => {
            const hash = window.location.hash;
            if (hash) {
                setTimeout(() => {
                    const element = document.querySelector(hash);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 500);
            }
        });
    </script>
@endscript
