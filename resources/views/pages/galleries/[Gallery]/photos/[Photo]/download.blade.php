<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\View\View;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('galleries.photos.download');

middleware(['auth', 'verified', 'can:view,photo']);

render(function (View $view, Photo $photo) {
    return $photo->download();
}); ?>
