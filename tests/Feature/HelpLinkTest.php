<?php

use App\Support\Help\HelpLink;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('help.url', 'https://docs.picstome.com');
    app()->setLocale('en');
});

it('builds the English url for a mapped route', function () {
    expect(app(HelpLink::class)->url('galleries'))
        ->toBe('https://docs.picstome.com/start/first-gallery/');
});

it('prefixes the docs url for Spanish', function () {
    app()->setLocale('es');

    expect(app(HelpLink::class)->url('galleries'))
        ->toBe('https://docs.picstome.com/es/start/first-gallery/');
});

it('uses the article for a gallery and its photo viewer', function () {
    $help = app(HelpLink::class);

    expect($help->url('galleries.show'))->toBe('https://docs.picstome.com/sharing/')
        ->and($help->url('galleries.photos.show'))->toBe('https://docs.picstome.com/culling/rating/');
});

it('falls back to the route prefix for child routes', function () {
    $help = app(HelpLink::class);

    expect($help->url('contracts.download'))->toBe('https://docs.picstome.com/business/contracts/')
        ->and($help->url('invoices.pdf'))->toBe('https://docs.picstome.com/money/invoices/')
        ->and($help->url('branding.logos'))->toBe('https://docs.picstome.com/brand/public-profile/');
});

it('keeps exact matches ahead of the prefix fallback', function () {
    expect(app(HelpLink::class)->url('branding.watermark'))
        ->toBe('https://docs.picstome.com/brand/watermark/');
});

it('sends the account settings to the account article', function () {
    $help = app(HelpLink::class);

    expect($help->url('settings.profile'))->toBe('https://docs.picstome.com/start/account/')
        ->and($help->url('settings.password'))->toBe('https://docs.picstome.com/start/account/')
        ->and($help->url('settings.appearance'))->toBe('https://docs.picstome.com/start/account/');
});

it('opens the help home for an unknown route', function () {
    expect(app(HelpLink::class)->url('photoshoots'))
        ->toBe('https://docs.picstome.com/');
});

it('tolerates a trailing slash in the configured url', function () {
    config()->set('help.url', 'https://docs.picstome.com/');

    expect(app(HelpLink::class)->url('dashboard'))
        ->toBe('https://docs.picstome.com/start/what-picstome-does/');
});

it('maps only existing application routes', function () {
    foreach (array_keys(config('help.routes')) as $route) {
        expect(Route::has($route))->toBeTrue();
    }
});
