<?php

use App\Models\Gallery;

use function Laravel\Folio\render;

render(function (Gallery $gallery) {
    return to_route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);
}) ?>
