<?php

use App\Exceptions\BookingLimitReachedException;
use App\Models\AvailabilityWindow;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\Booking\BookingPageGate;
use App\Services\Booking\BookingPlanLimitService;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => [$this->user->email],
        'picstome.payments_fake' => true,
        'app.debug' => true,
    ]);

    BookingSetting::forTeam($this->team)->update([
        'timezone' => 'Europe/Madrid',
        'min_notice_hours' => 0,
        'buffer_minutes' => 15,
    ]);

    AvailabilityWindow::factory()
        ->for($this->team)
        ->forWeekday(1, '09:00:00', '13:00:00')
        ->create();

    $this->product = Product::factory()->for($this->team)->create([
        'duration_minutes' => 60,
        'price' => 20000,
    ]);

    $this->start = CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid');
});

it('allows one booking on the free plan and blocks the second', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    $service = app(BookingService::class);

    $service->hold($this->team, $this->product, $this->start, [
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);

    expect($this->team->fresh()->monthly_payment_limit)->toBe(1)
        ->and(app(BookingPlanLimitService::class)->used($this->team))->toBe(1);

    expect(fn () => $service->hold($this->team, $this->product, $this->start->addHours(2), [
        'name' => 'Luis',
        'email' => 'luis@example.com',
    ]))->toThrow(BookingLimitReachedException::class);
});

it('frees the monthly allowance when the booking is cancelled', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    $service = app(BookingService::class);

    $booking = $service->hold($this->team, $this->product, $this->start, [
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);

    $service->cancel($booking);

    $second = $service->hold($this->team, $this->product, $this->start->addHours(2), [
        'name' => 'Luis',
        'email' => 'luis@example.com',
    ]);

    expect($second->id)->not->toBeNull();
});

it('does not limit manual bookings made by the photographer', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    $service = app(BookingService::class);

    $service->hold($this->team, $this->product, $this->start, [
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);

    $manual = $service->createManual($this->team, $this->product, $this->start->addHours(2), [
        'name' => 'Luis',
        'email' => 'luis@example.com',
    ]);

    expect($manual->status->value)->toBe('confirmed');
});

it('does not limit teams without a monthly limit', function () {
    $this->team->update(['monthly_payment_limit' => null]);

    $service = app(BookingService::class);

    $service->hold($this->team, $this->product, $this->start, ['name' => 'Ana', 'email' => 'ana@example.com']);
    $service->hold($this->team, $this->product, $this->start->addHours(2), ['name' => 'Luis', 'email' => 'luis@example.com']);

    expect($this->team->bookings()->count())->toBe(2);
});

it('hides the public booking page when the monthly allowance is used', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    app(BookingService::class)->hold($this->team, $this->product, $this->start, [
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);

    expect(app(BookingPageGate::class)->canBook($this->team->fresh()))->toBeFalse();

    get('/@'.$this->team->handle.'/products')->assertNotFound();
});

it('keeps teams isolated in the limit count', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    $otherTeam = Team::factory()->create(['monthly_payment_limit' => 1]);
    $otherProduct = Product::factory()->for($otherTeam)->create();

    app(BookingService::class)->hold($this->team, $this->product, $this->start, [
        'name' => 'Ana',
        'email' => 'ana@example.com',
    ]);

    expect($this->team->bookings()->count())->toBe(1)
        ->and($otherTeam->bookings()->count())->toBe(0);
});
