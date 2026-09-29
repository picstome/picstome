<?php

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to the app locale when the browser sends a wildcard Accept-Language', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create();

    $this->withHeaders(['Accept-Language' => '*'])
        ->get("/shares/{$gallery->ulid}/unlock")
        ->assertSuccessful();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

it('applies the locale requested by the browser', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create();

    $this->withHeaders(['Accept-Language' => 'es-MX,es;q=0.8,en;q=0.5'])
        ->get("/shares/{$gallery->ulid}/unlock")
        ->assertSuccessful();

    expect(app()->getLocale())->toBe('es');
});
