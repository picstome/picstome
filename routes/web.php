<?php

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

Route::get('/shared-moodboards/{moodboard:ulid}', function (\App\Models\Moodboard $moodboard) {
    return redirect()->route('shared-moodboards.show', ['moodboard' => $moodboard, 'slug' => $moodboard->slug]);
})->name('shared-moodboards.redirect');

Volt::route('/shared-moodboards/{moodboard:ulid}/{slug}', 'pages.shared-moodboards.show')->name('shared-moodboards.show');
