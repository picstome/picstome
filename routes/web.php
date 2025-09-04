<?php

use App\Http\Controllers\HandleController;
use Illuminate\Support\Facades\Route;

Route::get('/bio-links', function () {
    return view('pages.bio-links');
})->middleware(['auth'])->name('bio-links.index');

// Handle routes - must be last to not conflict with other routes
Route::get('/@{handle}', [HandleController::class, 'show'])->where('handle', '[a-zA-Z0-9_]+')->name('handle.show');
