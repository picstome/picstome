<?php

use App\Http\Middleware\PasswordProtectGallery;
use App\Models\Gallery;
use App\Models\Moodboard;
use App\Models\Photo;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/@{handle}', 'pages.handle.show')->where('handle', '[a-zA-Z0-9_]+')->name('handle.show');

Volt::route('/@{handle}/pay/{amount}/{description}', 'pages.pay.show')->where('handle', '[a-zA-Z0-9_]+')->name('handle.pay');

Route::get('/@{handle}/pay/', function ($handle) {
    return redirect()->route('handle.show', ['handle' => $handle, 'pay' => 1]);
})->where('handle', '[a-zA-Z0-9_]+');
Volt::route('/@{handle}/pay/success', 'pages.pay.success')->where('handle', '[a-zA-Z0-9_]+')->name('handle.pay.success');
Volt::route('/@{handle}/pay/cancel', 'pages.pay.cancel')->where('handle', '[a-zA-Z0-9_]+')->name('handle.pay.cancel');

Volt::route('/@{handle}/portfolio', 'pages.portfolio.index')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.index');
Volt::route('/@{handle}/portfolio/{gallery:ulid}', 'pages.portfolio.show')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.show');
Volt::route('/@{handle}/portfolio/{gallery:ulid}/photos/{photo}', 'pages.portfolio.photos.show')->name('portfolio.photos.show');

Volt::route('/moodboards', 'pages.moodboards')->name('moodboards')->middleware(['auth', 'verified']);

Volt::route('/moodboards/{moodboard}', 'pages.moodboards.show')->name('moodboards.show')->middleware(['auth', 'verified']);

Route::get('/shared-moodboards/{moodboard:ulid}', function (Moodboard $moodboard) {
    return redirect()->route('shared-moodboards.show', ['moodboard' => $moodboard, 'slug' => $moodboard->slug]);
})->name('shared-moodboards.redirect');

Volt::route('/shared-moodboards/{moodboard:ulid}/{slug}', 'pages.shared-moodboards.show')->name('shared-moodboards.show');

Volt::route('/shares/{gallery:ulid}/unlock', 'pages.shares.unlock')->name('shares.unlock');

Route::get('/shares/{gallery:ulid}/download', function (Gallery $gallery) {
    abort_unless($gallery->is_shared, 404);

    abort_unless($gallery->is_share_downloadable, 401);

    return $gallery->download((bool) request()->input('favorites'));
})->name('shares.download')->middleware([PasswordProtectGallery::class]);

Volt::route('/shares/{gallery:ulid}/photos/{photo}', 'pages.shares.photos.show')->name('shares.photos.show')->middleware([PasswordProtectGallery::class]);

Route::get('/shares/{gallery:ulid}/photos/{photo}/download', function (Gallery $gallery, Photo $photo) {
    abort_unless($photo->gallery->is_shared, 404);

    abort_unless($photo->gallery->is_share_downloadable, 401);

    $type = request()->input('type', 'processed');

    if ($type === 'raw') {
        return $photo->downloadRaw();
    }

    return $photo->download();
})->name('shares.photos.download')->middleware([PasswordProtectGallery::class]);

Route::get('/shares/{gallery:ulid}', function (Gallery $gallery) {
    return redirect('/shares/'.$gallery.'/'.$gallery->slug);
})->name('shares.redirect');

Volt::route('/shares/{gallery:ulid}/{slug}', 'pages.shares.show')->name('shares.show')->middleware([PasswordProtectGallery::class]);

Volt::route('/contract-templates', 'pages.contract-templates')->name('contract-templates')->middleware(['auth', 'verified']);

Volt::route('/contract-templates/{contractTemplate}', 'pages.contract-templates.show')->name('contract-templates.show')->middleware(['auth', 'verified']);

Volt::route('/contracts', 'pages.contracts')->name('contracts')->middleware(['auth', 'verified']);

Volt::route('/contracts/{contract}', 'pages.contracts.show')->name('contracts.show')->middleware(['auth', 'verified']);

Volt::route('/branding', 'pages.branding')->name('branding')->middleware('auth');

Volt::route('/branding/general', 'pages.branding.general')->name('branding.general')->middleware('auth');

Volt::route('/branding/logos', 'pages.branding.logos')->name('branding.logos')->middleware('auth');

Volt::route('/branding/payments', 'pages.branding.payments')->name('branding.payments')->middleware(['auth', 'verified']);

Volt::route('/branding/styling', 'pages.branding.styling')->name('branding.styling')->middleware('auth');

Volt::route('/branding/watermark', 'pages.branding.watermark')->name('branding.watermark')->middleware('auth');

Volt::route('/customers', 'pages.customers')->name('customers')->middleware(['auth', 'verified']);

Volt::route('/customers/{customer}', 'pages.customers.show')->name('customers.show')->middleware(['auth', 'verified']);

Volt::route('/galleries', 'pages.galleries')->name('galleries')->middleware(['auth', 'verified']);
Volt::route('/galleries/{gallery}', 'pages.galleries.show')->name('galleries.show')->middleware(['auth', 'verified']);
Route::get('/galleries/{gallery}/download', function (Gallery $gallery) {
    return $gallery->download();
})->name('galleries.download')->middleware(['auth', 'verified', 'can:view,gallery']);
Volt::route('/galleries/{gallery}/photos/{photo}', 'pages.galleries.photos.show')->name('galleries.photos.show')->middleware(['auth', 'verified']);

Volt::route('/photoshoots', 'pages.photoshoots')->name('photoshoots')->middleware(['auth', 'verified']);
Volt::route('/photoshoots/{photoshoot}', 'pages.photoshoots.show')->name('photoshoots.show')->middleware(['auth', 'verified']);

Volt::route('/signatures/{signature}/sign', 'pages.signatures.sign')->name('signatures.sign');

Route::get('/galleries/{gallery}/photos/{photo}/download', function (Gallery $gallery, Photo $photo) {
    $type = request('type', 'processed');

    if ($type === 'raw') {
        return $photo->downloadRaw();
    }

    return $photo->download();
})->name('galleries.photos.download')->middleware(['auth', 'verified', 'can:view,photo']);
