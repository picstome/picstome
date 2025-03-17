<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\View\View;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('shares.photos.download');

middleware([PasswordProtectGallery::class]);

render(function (View $view, Photo $photo) {
    abort_unless($photo->gallery->is_shared, 404);

    abort_unless($photo->gallery->is_share_downloadable, 401);

    return $photo->download();
}); ?>
