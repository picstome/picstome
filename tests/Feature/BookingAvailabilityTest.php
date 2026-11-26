<?php

use App\Models\AvailabilityWindow;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\CalendarBlock;
use App\Models\Product;
use App\Models\Team;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Monday 1 June 2026, 07:00 in Madrid (05:00 UTC).
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));
});

/**
 * Team, product and configuration with a Monday window.
 *
 * @param  array<string, mixed>  $settings
 * @param  array<int, array{0: int, 1: string, 2: string}>  $windows
 * @param  array<string, mixed>  $product
 * @return array{0: Team, 1: Product}
 */
function bookingAvailabilityScenario(array $settings = [], array $windows = [[1, '09:00:00', '13:00:00']], array $product = []): array
{
    $team = Team::factory()->create();

    BookingSetting::forTeam($team)->update(array_merge([
        'timezone' => 'Europe/Madrid',
        'min_notice_hours' => 0,
        'slot_step_minutes' => 30,
        'buffer_minutes' => 15,
        'horizon_days' => 90,
        'max_sessions_per_day' => null,
    ], $settings));

    foreach ($windows as [$weekday, $start, $end]) {
        AvailabilityWindow::factory()
            ->for($team)
            ->forWeekday($weekday, $start, $end)
            ->create();
    }

    $productModel = Product::factory()->for($team)->create(array_merge([
        'duration_minutes' => 60,
        'price' => 15000,
        'buffer_minutes' => null,
    ], $product));

    return [$team->fresh(), $productModel];
}

/**
 * Local start times (HH:MM) offered for a date.
 *
 * @return array<int, string>
 */
function bookingSlotTimes(Team $team, Product $product, string $date): array
{
    $timezone = BookingSetting::forTeam($team)->timezone;

    return app(AvailabilityService::class)
        ->slotsForDate($team, $product, CarbonImmutable::parse($date, $timezone))
        ->map(fn ($slot) => $slot->startsAt->setTimezone($timezone)->format('H:i'))
        ->all();
}

it('offers starts inside the window respecting duration and step', function () {
    [$team, $product] = bookingAvailabilityScenario();

    expect(bookingSlotTimes($team, $product, '2026-06-01'))->toBe([
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00',
    ]);
});

it('does not offer a session that does not fit before closing time', function () {
    [$team, $product] = bookingAvailabilityScenario(
        windows: [[1, '09:00:00', '10:15:00']],
    );

    expect(bookingSlotTimes($team, $product, '2026-06-01'))->toBe(['09:00']);
});

it('keeps the buffer between sessions', function () {
    [$team, $product] = bookingAvailabilityScenario();

    Booking::factory()->for($team)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);

    expect(bookingSlotTimes($team, $product, '2026-06-01'))->toBe([
        '10:30', '11:00', '11:30', '12:00',
    ]);
});

it('ignores holds whose payment window already closed', function () {
    [$team, $product] = bookingAvailabilityScenario();

    Booking::factory()->for($team)->pending()->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
        'hold_expires_at' => now()->subMinute(),
    ]);

    expect(bookingSlotTimes($team, $product, '2026-06-01'))->toContain('09:00');
});

it('keeps a slot blocked while its hold is active', function () {
    [$team, $product] = bookingAvailabilityScenario();

    Booking::factory()->for($team)->pending()->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    expect(bookingSlotTimes($team, $product, '2026-06-01'))->not->toContain('09:00', '09:30', '10:00');
});

it('respects the minimum notice', function () {
    [$team, $product] = bookingAvailabilityScenario(
        settings: ['min_notice_hours' => 2],
        windows: [[1, '08:00:00', '12:00:00']],
    );

    $slots = bookingSlotTimes($team, $product, '2026-06-01');

    expect($slots)->toContain('09:00')
        ->and($slots)->not->toContain('08:00', '08:30');
});

it('refuses dates beyond the horizon', function () {
    [$team, $product] = bookingAvailabilityScenario(
        settings: ['horizon_days' => 7],
    );

    expect(bookingSlotTimes($team, $product, '2026-06-15'))->toBe([]);
});

it('stops offering slots when the daily cap is reached', function () {
    [$team, $product] = bookingAvailabilityScenario(
        settings: ['max_sessions_per_day' => 2],
    );

    Booking::factory()->for($team)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);

    Booking::factory()->for($team)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 11:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 12:00', 'Europe/Madrid'),
    ]);

    expect(bookingSlotTimes($team, $product, '2026-06-01'))->toBe([]);
});

it('removes the slots covered by a calendar block', function () {
    [$team, $product] = bookingAvailabilityScenario();

    CalendarBlock::factory()->for($team)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 11:00', 'Europe/Madrid'),
        'all_day' => false,
        'reason' => 'Doctor',
    ]);

    $slots = bookingSlotTimes($team, $product, '2026-06-01');

    expect($slots)->toContain('09:00', '11:00')
        ->and($slots)->not->toContain('10:00', '10:30');
});

it('skips the hour that does not exist on the spring forward night', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-28 20:00:00', 'UTC'));

    [$team, $product] = bookingAvailabilityScenario(
        windows: [[7, '00:00:00', '06:00:00']],
        product: ['duration_minutes' => 60],
    );

    BookingSetting::forTeam($team)->update(['slot_step_minutes' => 60]);

    expect(bookingSlotTimes($team->fresh(), $product, '2026-03-29'))->toBe([
        '00:00', '01:00', '03:00', '04:00', '05:00',
    ]);
});

it('does not duplicate slot labels on the fall back night', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 20:00:00', 'UTC'));

    [$team, $product] = bookingAvailabilityScenario(
        windows: [[7, '00:00:00', '06:00:00']],
        product: ['duration_minutes' => 60],
    );

    BookingSetting::forTeam($team)->update(['slot_step_minutes' => 60]);

    expect(bookingSlotTimes($team->fresh(), $product, '2026-10-25'))->toBe([
        '00:00', '01:00', '02:00', '03:00', '04:00', '05:00',
    ]);
});
