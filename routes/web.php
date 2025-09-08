<?php

use App\Http\Controllers\HandleController;
use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

// Handle routes - must be last to not conflict with other routes
Route::get('/@{handle}', [HandleController::class, 'show'])->where('handle', '[a-zA-Z0-9_]+')->name('handle.show');
Route::get('/@{handle}/portfolio/{gallery}', [PortfolioController::class, 'show'])->where('handle', '[a-zA-Z0-9_]+')->name('portfolio.show');
