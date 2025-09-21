<?php

use App\Http\Controllers\HandleController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\StripeConnectController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Route::get('/stripe-connect', [StripeConnectController::class, 'index'])->name('stripe.connect');
Route::get('/stripe-connect/refresh', [StripeConnectController::class, 'refresh'])->name('stripe.connect.refresh');
Route::get('/stripe-connect/return', [StripeConnectController::class, 'return'])->name('stripe.connect.return');

// Handle routes - must be last to not conflict with other routes
Route::get('/@{handle}', [HandleController::class, 'show'])->where('handle', '[a-zA-Z0-9_]+')->name('handle.show');
Volt::route('/@{handle}/portfolio', 'pages.portfolio.index')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.index');
Volt::route('/@{handle}/portfolio/{gallery:ulid}', 'pages.portfolio.show')->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.show');
Volt::route('/@{handle}/portfolio/{gallery:ulid}/photos/{photo}', 'pages.portfolio.photos.show')->name('portfolio.photos.show');
