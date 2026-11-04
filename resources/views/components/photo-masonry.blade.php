{{--
    Masonry photo wall shared by the owner gallery, the public shared gallery
    and the public portfolio.

    CSS multi-column keeps each photo at its natural aspect ratio, so nothing
    is cropped. The trade-off is that columns fill top-to-bottom instead of
    left-to-right, which avoids a JavaScript masonry dependency.
--}}

@props([
    'photos',
    'gallery',
    'context' => 'owner',
    'htmlIdPrefix' => 'photo',
    'asFavorite' => false,
    'asCommented' => false,
])

@php
    $columns = $context === 'owner'
        ? 'columns-2 sm:columns-3 xl:columns-4'
        : 'columns-1 sm:columns-2 md:columns-3 lg:columns-4 2xl:columns-5';
@endphp

<div {{ $attributes->class(['gap-1', $columns]) }} data-photo-masonry>
    @foreach ($photos as $photo)
        <x-photo-tile
            :$photo
            :$gallery
            :$context
            :as-favorite="$asFavorite"
            :as-commented="$asCommented"
            :html-id="$htmlIdPrefix.'-'.$photo->id"
            :index="$loop->index"
        />
    @endforeach
</div>
