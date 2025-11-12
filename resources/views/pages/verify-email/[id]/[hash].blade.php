<?php

use App\Jobs\AddToAcumbamailList;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

middleware(['auth', 'signed', 'throttle:6,1']);

name('verification.verify');

render(function (EmailVerificationRequest $request) {
    if ($request->user()->hasVerifiedEmail()) {
        return redirect()->intended(route('galleries', absolute: false).'?verified=1');
    }

    $request->fulfill();

    $listId = app()->getLocale() === 'es'
        ? config('services.acumbamail.list_id_es')
        : config('services.acumbamail.list_id');

    AddToAcumbamailList::dispatch($request->user()->email, $request->user()->name, $listId);

    return redirect()->intended(route('galleries', absolute: false).'?verified=1');
}); ?>
