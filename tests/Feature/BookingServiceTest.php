<?php

use App\Enums\BookingStatus;
use App\Enums\DepositType;
use App\Enums\PaymentMethod;
use App\Exceptions\SlotUnavailableException;
use App\Models\AvailabilityWindow;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Models\Team;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Monday 1 June 2026, 07:00 in Madrid (05:00 UTC).
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));
});

/**
 * @param  array<string, mixed>  $settings
 * @param  array<string, mixed>  $product
 * @return array{0: Team, 1: Product}
 */
function bookingServiceScenario(array $settings = [], array $product = []): array
{
    $team = Team::factory()->create();

    BookingSetting::forTeam($team)->update(array_merge([
        'timezone' => 'Europe/Madrid',
        'min_notice_hours' => 0,
        'slot_step_minutes' => 30,
        'buffer_minutes' => 15,
        'horizon_days' => 90,
    ], $settings));

    AvailabilityWindow::factory()
        ->for($team)
        ->forWeekday(1, '09:00:00', '13:00:00')
        ->create();

    $productModel = Product::factory()->for($team)->create(array_merge([
        'duration_minutes' => 60,
        'price' => 20000,
        'buffer_minutes' => null,
    ], $product));

    return [$team->fresh(), $productModel];
}

function bookingStart(string $time = '09:00'): CarbonImmutable
{
    return CarbonImmutable::parse('2026-06-01 '.$time, 'Europe/Madrid');
}

it('creates a hold with a fixed deposit and the remaining balance', function () {
    [$team, $product] = bookingServiceScenario(product: [
        'deposit_type' => DepositType::Fixed,
        'deposit_value' => 5000,
    ]);

    $booking = app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart(),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    );

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->total_amount)->toBe(20000)
        ->and($booking->deposit_amount)->toBe(5000)
        ->and($booking->balance_amount)->toBe(15000)
        ->and($booking->hold_expires_at)->not->toBeNull()
        ->and($booking->policy_accepted_at)->toBeNull();
});

it('uses the team deposit when the product does not define one', function () {
    [$team, $product] = bookingServiceScenario(
        settings: [
            'default_deposit_type' => DepositType::Percent,
            'default_deposit_value' => 30,
        ],
        product: [
            'deposit_type' => null,
            'deposit_value' => null,
        ],
    );

    $booking = app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart(),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    );

    expect($booking->deposit_amount)->toBe(6000)
        ->and($booking->balance_amount)->toBe(14000);
});

it('freezes the cancellation policy accepted by the client', function () {
    [$team, $product] = bookingServiceScenario();

    $booking = app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart(),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
        policy: 'The deposit is not refundable.',
    );

    expect($booking->policy_snapshot)->toBe('The deposit is not refundable.')
        ->and($booking->policy_accepted_at)->not->toBeNull();
});

it('rejects a slot that overlaps another booking', function () {
    [$team, $product] = bookingServiceScenario();

    app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart('09:00'),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    );

    expect(fn () => app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart('09:30'),
        ['name' => 'Luis', 'email' => 'luis@example.com'],
    ))->toThrow(SlotUnavailableException::class);
});

it('rejects a product from another team', function () {
    [$team] = bookingServiceScenario();
    [, $foreignProduct] = bookingServiceScenario();

    expect(fn () => app(BookingService::class)->hold(
        $team,
        $foreignProduct,
        bookingStart(),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    ))->toThrow(InvalidArgumentException::class);
});

it('expires pending holds and frees the slot again', function () {
    [$team, $product] = bookingServiceScenario();

    $booking = app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart('09:00'),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    );

    $this->travel(20)->minutes();

    expect(app(BookingService::class)->expirePending())->toBe(1)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Expired);

    $newBooking = app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart('09:00'),
        ['name' => 'Luis', 'email' => 'luis@example.com'],
    );

    expect($newBooking->status)->toBe(BookingStatus::PendingPayment);
});

it('confirms a pending booking and clears the hold', function () {
    [$team, $product] = bookingServiceScenario();

    $booking = app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart(),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    );

    app(BookingService::class)->confirm($booking, PaymentMethod::Card);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->hold_expires_at)->toBeNull()
        ->and($booking->fresh()->confirmed_at)->not->toBeNull()
        ->and($booking->fresh()->payment_method)->toBe(PaymentMethod::Card);
});

it('reports a slot as available only while nothing blocks it', function () {
    [$team, $product] = bookingServiceScenario();
    $availability = app(AvailabilityService::class);

    expect($availability->isAvailable($team, $product, bookingStart('09:00')))->toBeTrue();

    app(BookingService::class)->hold(
        $team,
        $product,
        bookingStart('09:00'),
        ['name' => 'Ana', 'email' => 'ana@example.com'],
    );

    expect($availability->isAvailable($team, $product, bookingStart('09:00')))->toBeFalse();
});
