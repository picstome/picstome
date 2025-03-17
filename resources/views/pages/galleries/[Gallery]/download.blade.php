<?php

use App\Models\Gallery;
use Illuminate\View\View;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('galleries.download');

middleware(['auth', 'can:view,gallery']);

render(function (View $view, Gallery $gallery) {
    return $gallery->download();
}); ?>
