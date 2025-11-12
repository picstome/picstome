<?php

use Illuminate\Support\Facades\Auth;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding');

middleware('auth');

render(function () {
    return redirect()->route('branding.general');
});
