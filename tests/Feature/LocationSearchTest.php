<?php

use App\Services\Booking\LocationSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('resolves a place name to coordinates', function () {
    Http::fake([
        'photon.komoot.io/*' => Http::response([
            'features' => [
                [
                    'geometry' => ['coordinates' => [-0.8891, 41.6488]],
                    'properties' => [
                        'name' => 'Plaza del Pilar',
                        'city' => 'Zaragoza',
                        'country' => 'España',
                    ],
                ],
            ],
        ]),
    ]);

    $results = app(LocationSearch::class)->search('Plaza del Pilar');

    expect($results)->toHaveCount(1)
        ->and($results[0]['label'])->toBe('Plaza del Pilar, Zaragoza, España')
        ->and($results[0]['latitude'])->toBe(41.6488)
        ->and($results[0]['longitude'])->toBe(-0.8891);
});

it('caches a search so repeated queries do not hit the API', function () {
    Http::fake([
        'photon.komoot.io/*' => Http::response([
            'features' => [
                [
                    'geometry' => ['coordinates' => [-0.8891, 41.6488]],
                    'properties' => ['name' => 'Plaza del Pilar', 'city' => 'Zaragoza'],
                ],
            ],
        ]),
    ]);

    $search = app(LocationSearch::class);

    $search->search('Plaza del Pilar');
    $search->search('Plaza del Pilar');

    Http::assertSentCount(1);
});

it('does not call the API for a very short query', function () {
    Http::fake();

    expect(app(LocationSearch::class)->search('Za'))->toBeEmpty();

    Http::assertNothingSent();
});

it('returns no results when the API fails', function () {
    Http::fake([
        'photon.komoot.io/*' => Http::response('down', 500),
    ]);

    expect(app(LocationSearch::class)->search('Zaragoza'))->toBeEmpty();
});
