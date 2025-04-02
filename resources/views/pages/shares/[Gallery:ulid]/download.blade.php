<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\View\View;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('shares.download');

middleware([PasswordProtectGallery::class]);

render(function (Request $request, Gallery $gallery) {
    abort_unless($gallery->is_shared, 404);

    abort_unless($gallery->is_share_downloadable, 401);

    return $gallery->download((bool) $request->input('favorites'));
}); ?>
