<?php

use App\Models\CalendarBlock;
use App\Support\MonthGrid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a monday-first grid covering the whole weeks of a month', function () {
    $days = app(MonthGrid::class)->days(CarbonImmutable::parse('2026-06-01'));

    expect($days[0]->dayOfWeekIso)->toBe(1)
        ->and($days[array_key_last($days)]->dayOfWeekIso)->toBe(7)
        ->and(count($days) % 7)->toBe(0)
        ->and($days[0]->toDateString())->toBe('2026-06-01')
        ->and(collect($days)->contains(fn ($day) => $day->toDateString() === '2026-06-30'))->toBeTrue();
});

it('detects whether a block covers a whole day', function () {
    $dayStart = CarbonImmutable::parse('2026-06-05 00:00', 'Europe/Madrid');
    $dayEnd = $dayStart->endOfDay()->startOfSecond();

    $fullDay = new CalendarBlock([
        'starts_at' => $dayStart,
        'ends_at' => $dayEnd,
    ]);

    $partial = new CalendarBlock([
        'starts_at' => $dayStart->setTime(10, 0),
        'ends_at' => $dayStart->setTime(12, 0),
    ]);

    expect($fullDay->coversDay($dayStart, $dayEnd))->toBeTrue()
        ->and($partial->coversDay($dayStart, $dayEnd))->toBeFalse();
});
