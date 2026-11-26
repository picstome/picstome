<?php

use App\Enums\LightMode;
use App\Services\Booking\Slot;
use App\Services\Booking\SunlightService;
use Carbon\CarbonImmutable;

it('calculates the sunset of a place and a day', function () {
    $sunset = app(SunlightService::class)->sunTime(
        CarbonImmutable::parse('2026-06-21', 'Europe/Madrid'),
        41.6488,
        -0.8891,
        'Europe/Madrid',
        LightMode::Sunset,
    );

    expect($sunset)->not->toBeNull()
        ->and($sunset->format('H:i'))->toBeGreaterThanOrEqual('21:00')
        ->and($sunset->format('H:i'))->toBeLessThanOrEqual('22:30');
});

it('recommends the slot that ends at sunset', function () {
    $date = CarbonImmutable::parse('2026-06-21', 'Europe/Madrid');

    $slots = collect(['19:00', '20:00', '21:00'])->map(function (string $time) use ($date) {
        $start = CarbonImmutable::parse($date->format('Y-m-d').' '.$time, 'Europe/Madrid');

        return new Slot($start->utc(), $start->addHour()->utc());
    })->all();

    $recommended = app(SunlightService::class)->recommendedStart(
        $slots,
        LightMode::Sunset,
        $date,
        41.6488,
        -0.8891,
        'Europe/Madrid',
        60,
    );

    expect($recommended)->not->toBeNull()
        ->and($recommended->setTimezone('Europe/Madrid')->format('H:i'))->toBe('21:00');
});

it('does not recommend anything when the light is not relevant', function () {
    $date = CarbonImmutable::parse('2026-06-21', 'Europe/Madrid');
    $start = $date->setTime(10, 0);

    $recommended = app(SunlightService::class)->recommendedStart(
        [new Slot($start->utc(), $start->addHour()->utc())],
        LightMode::None,
        $date,
        41.6488,
        -0.8891,
        'Europe/Madrid',
        60,
    );

    expect($recommended)->toBeNull();
});
