{{--
    Masonry photo wall shared by the owner gallery, the public shared gallery
    and the public portfolio.

    CSS multi-column is the no-JavaScript fallback. When Alpine is available,
    photoMasonry takes over and positions each tile absolutely so "load more"
    appends new photos below the existing wall instead of rebalancing every
    column. The CSS columns stay in place for the fallback and for the tests
    that inspect the responsive column count.
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
    $breakpoints = $context === 'owner' ? '0,640,1280' : '0,640,768,1024,1536';
    $columnCounts = $context === 'owner' ? '2,3,4' : '1,2,3,4,5';
@endphp

<div
    {{ $attributes->class(['relative', 'gap-1', $columns]) }}
    data-photo-masonry
    data-masonry-breakpoints="{{ $breakpoints }}"
    data-masonry-columns="{{ $columnCounts }}"
    data-masonry-gap="4"
    x-data="photoMasonry"
    wire:key="photo-masonry-{{ $context }}-{{ $htmlIdPrefix }}"
>
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
