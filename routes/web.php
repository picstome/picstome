<?php

use App\Http\Controllers\HandleController;
use App\Http\Controllers\StripeConnectController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/stripe-connect/pay', [StripeConnectController::class, 'pay'])->name('stripe.connect.pay');
Route::get('/stripe-connect/pay/success', [StripeConnectController::class, 'paySuccess'])->name('stripe.connect.pay.success');
Route::get('/stripe-connect/pay/cancel', [StripeConnectController::class, 'payCancel'])->name('stripe.connect.pay.cancel');

// Handle routes - must be last to not conflict with other routes
Route::get('/@{handle}', [HandleController::class, 'show'])->where('handle', '[a-zA-Z0-9_]+')->name('handle.show');
Volt::route('/@{handle}/portfolio', 'pages.portfolio.index')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.index');
Volt::route('/@{handle}/portfolio/{gallery:ulid}', 'pages.portfolio.show')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.show');
Volt::route('/@{handle}/portfolio/{gallery:ulid}/photos/{photo}', 'pages.portfolio.photos.show')->name('portfolio.photos.show');
