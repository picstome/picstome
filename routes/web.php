<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\PasswordProtectGallery;
use App\Jobs\AddToAcumbamailList;
use App\Models\Gallery;
use App\Models\Moodboard;
use App\Models\Photo;
use Facades\App\Services\StripeConnectService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/@{handle}', 'pages::handle.show')->where('handle', '[a-zA-Z0-9_]+')->name('handle.show');

Route::livewire('/@{handle}/pay/{amount}/{description}', 'pages::pay.show')->where('handle', '[a-zA-Z0-9_]+')->name('handle.pay');

Route::get('/@{handle}/pay/', function ($handle) {
    return redirect()->route('handle.show', ['handle' => $handle, 'pay' => 1]);
})->where('handle', '[a-zA-Z0-9_]+');
Route::livewire('/@{handle}/pay/success', 'pages::pay.success')->where('handle', '[a-zA-Z0-9_]+')->name('handle.pay.success');
Route::livewire('/@{handle}/pay/cancel', 'pages::pay.cancel')->where('handle', '[a-zA-Z0-9_]+')->name('handle.pay.cancel');

Route::livewire('/@{handle}/portfolio', 'pages::portfolio.index')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.index');
Route::livewire('/@{handle}/portfolio/{gallery:ulid}', 'pages::portfolio.show')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.show');
Route::livewire('/@{handle}/portfolio/{gallery:ulid}/photos/{photo}', 'pages::portfolio.photos.show')->name('portfolio.photos.show');

Route::livewire('/moodboards', 'pages::moodboards')->name('moodboards')->middleware(['auth', 'verified']);

Route::livewire('/moodboards/{moodboard}', 'pages::moodboards.show')->name('moodboards.show')->middleware(['auth', 'verified']);

Route::get('/shared-moodboards/{moodboard:ulid}', function (Moodboard $moodboard) {
    return redirect()->route('shared-moodboards.show', ['moodboard' => $moodboard, 'slug' => $moodboard->slug]);
})->name('shared-moodboards.redirect');

Route::livewire('/shared-moodboards/{moodboard:ulid}/{slug}', 'pages::shared-moodboards.show')->name('shared-moodboards.show');

Route::livewire('/shares/{gallery:ulid}/unlock', 'pages::shares.unlock')->name('shares.unlock');

Route::get('/shares/{gallery:ulid}/download', function (Gallery $gallery) {
    abort_unless($gallery->is_shared, 404);

    abort_unless($gallery->is_share_downloadable, 401);

    return $gallery->download((bool) request()->input('favorites'));
})->name('shares.download')->middleware([PasswordProtectGallery::class]);

Route::livewire('/shares/{gallery:ulid}/photos/{photo}', 'pages::shares.photos.show')->name('shares.photos.show')->middleware([PasswordProtectGallery::class]);

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

Route::livewire('/shares/{gallery:ulid}/{slug}', 'pages::shares.show')->name('shares.show')->middleware([PasswordProtectGallery::class]);

Route::livewire('/contract-templates', 'pages::contract-templates')->name('contract-templates')->middleware(['auth', 'verified']);

Route::livewire('/contract-templates/{contractTemplate}', 'pages::contract-templates.show')->name('contract-templates.show')->middleware(['auth', 'verified']);

Route::livewire('/contracts', 'pages::contracts')->name('contracts')->middleware(['auth', 'verified']);

Route::livewire('/contracts/{contract}', 'pages::contracts.show')->name('contracts.show')->middleware(['auth', 'verified']);

Route::redirect('/branding', '/branding/general')->name('branding')->middleware('auth');

Route::livewire('/branding/general', 'pages::branding.general')->name('branding.general')->middleware('auth');

Route::livewire('/branding/logos', 'pages::branding.logos')->name('branding.logos')->middleware('auth');

Route::livewire('/branding/payments', 'pages::branding.payments')->name('branding.payments')->middleware(['auth', 'verified']);

Route::livewire('/branding/styling', 'pages::branding.styling')->name('branding.styling')->middleware('auth');

Route::livewire('/branding/watermark', 'pages::branding.watermark')->name('branding.watermark')->middleware('auth');

Route::livewire('/customers', 'pages::customers')->name('customers')->middleware(['auth', 'verified']);

Route::livewire('/customers/{customer}', 'pages::customers.show')->name('customers.show')->middleware(['auth', 'verified']);

Route::livewire('/galleries', 'pages::galleries')->name('galleries')->middleware(['auth', 'verified']);
Route::livewire('/galleries/{gallery}', 'pages::galleries.show')->name('galleries.show')->middleware(['auth', 'verified']);
Route::get('/galleries/{gallery}/download', function (Gallery $gallery) {
    return $gallery->download();
})->name('galleries.download')->middleware(['auth', 'verified', 'can:view,gallery']);
Route::livewire('/galleries/{gallery}/photos/{photo}', 'pages::galleries.photos.show')->name('galleries.photos.show')->middleware(['auth', 'verified']);

Route::livewire('/photoshoots', 'pages::photoshoots')->name('photoshoots')->middleware(['auth', 'verified']);
Route::livewire('/photoshoots/{photoshoot}', 'pages::photoshoots.show')->name('photoshoots.show')->middleware(['auth', 'verified']);

Route::livewire('/signatures/{signature}/sign', 'pages::signatures.sign')->name('signatures.sign');

Route::livewire('/settings/appearance', 'pages::settings.appearance')->name('settings.appearance')->middleware('auth');

Route::livewire('/settings/password', 'pages::settings.password')->name('settings.password')->middleware('auth');

Route::livewire('/settings/profile', 'pages::settings.profile')->name('settings.profile')->middleware('auth');

Route::livewire('/tools/calculator', 'pages::tools.calculator')->name('tools.calculator')->middleware(['auth', 'verified']);

Route::livewire('/tools/invoice-generator', 'pages::tools.invoice-generator')->name('tools.invoice-generator')->middleware(['auth', 'verified']);

Route::livewire('/stripe-connect', 'pages::stripe-connect.index')->name('stripe.connect')->middleware(['auth', 'verified']);

Route::livewire('/stripe-connect/return', 'pages::stripe-connect.return')->name('stripe.connect.return')->middleware(['auth', 'verified']);

Route::get('/stripe-connect/refresh', function () {
    $team = Auth::user()->currentTeam;
    $onboardingUrl = StripeConnectService::createOnboardingLink($team);

    return redirect($onboardingUrl);
})->name('stripe.connect.refresh')->middleware(['auth', 'verified']);

Route::livewire('/reset-password/{token}', 'pages::reset-password.token')->name('password.reset')->middleware('guest');

Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
    if ($request->user()->hasVerifiedEmail()) {
        return redirect()->intended(route('galleries', absolute: false).'?verified=1');
    }

    $request->fulfill();

    $listId = app()->getLocale() === 'es'
        ? config('services.acumbamail.list_id_es')
        : config('services.acumbamail.list_id');

    AddToAcumbamailList::dispatch($request->user()->email, $request->user()->name, $listId);

    return redirect()->intended(route('galleries', absolute: false).'?verified=1');
})->name('verification.verify')->middleware(['auth', 'signed', 'throttle:6,1']);

Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard')->middleware(['auth', 'verified']);
Route::livewire('/forgot-password', 'pages::forgot-password')->name('password.request')->middleware('guest');
Route::livewire('/login', 'pages::login')->name('login')->middleware('guest');
Route::livewire('/payments', 'pages::payments')->name('payments')->middleware(['auth', 'verified']);
Route::livewire('/portfolio', 'pages::portfolio')->name('portfolio')->middleware(['auth', 'verified']);
Route::livewire('/public-profile', 'pages::public-profile')->name('public-profile')->middleware('auth');
Route::livewire('/register', 'pages::register')->name('register')->middleware('guest');
Route::livewire('/subscribe', 'pages::subscribe')->name('subscribe')->middleware(['auth', 'verified']);
Route::livewire('/users', 'pages::users')->name('users')->middleware(['auth', 'verified', EnsureUserIsAdmin::class]);
Route::livewire('/verify-email', 'pages::verify-email')->name('verification.notice')->middleware('auth');

Route::get('/billing-portal', function (Request $request) {
    $team = $request->user()->currentTeam;
    if (! $team->subscribed()) {
        return redirect()->route('subscribe');
    }

    return $team->redirectToBillingPortal(route('dashboard'));
})->name('billing-portal')->middleware(['auth', 'verified']);

Route::get('/', function () {
    return to_route('dashboard');
})->name('home');

Route::get('/product-checkout-success', function (Request $request) {
    $sessionId = $request->get('session_id');
    if (! $sessionId) {
        return redirect()->route('subscribe');
    }
    $team = $request->user()->currentTeam;
    $checkoutSession = $team->stripe()->checkout->sessions->retrieve($sessionId);
    if ($checkoutSession->payment_status === 'paid') {
        $team->update([
            'lifetime' => true,
            'custom_storage_limit' => config('picstome.subscription_storage_limit'),
        ]);

        return redirect()->route('dashboard')->with('success', 'Payment successful!');
    }

    return redirect()->route('subscribe');
})->name('product-checkout-success')->middleware(['auth', 'verified']);

Route::get('/settings', function () {
    return to_route('settings.profile');
})->name('settings')->middleware('auth');

Route::get('/galleries/{gallery}/photos/{photo}/download', function (Gallery $gallery, Photo $photo) {
    $type = request('type', 'processed');

    if ($type === 'raw') {
        return $photo->downloadRaw();
    }

    return $photo->download();
})->name('galleries.photos.download')->middleware(['auth', 'verified', 'can:view,photo']);
