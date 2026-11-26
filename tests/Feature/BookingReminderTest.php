<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BookingReminder as BookingReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00', 'UTC'));

    Notification::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    BookingSetting::forTeam($this->team)->update([
        'timezone' => 'Europe/Madrid',
        'reminder_first_enabled' => true,
        'reminder_first_hours' => 72,
        'reminder_second_enabled' => true,
        'reminder_second_hours' => 24,
    ]);

    $this->product = Product::factory()->for($this->team)->create([
        'name' => 'Sunset session',
        'location_name' => 'Playa',
    ]);

    $this->booking = Booking::factory()->for($this->team)->forProduct($this->product)->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => CarbonImmutable::parse('2026-06-10 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-10 10:00', 'Europe/Madrid'),
        'customer_email' => 'ana@example.com',
        'created_at' => now(),
    ]);
});

it('sends the 72 hour reminder once the window is open', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-07 10:00:00', 'UTC'));

    $this->artisan('bookings:send-reminders')->assertSuccessful();

    expect(BookingReminder::query()->where('booking_id', $this->booking->id)->where('type', 'reminder_first')->exists())->toBeTrue();

    Notification::assertSentOnDemand(BookingReminderNotification::class);
});

it('does not send the same reminder twice', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-07 10:00:00', 'UTC'));

    $this->artisan('bookings:send-reminders');
    $this->artisan('bookings:send-reminders');

    expect(BookingReminder::query()->where('booking_id', $this->booking->id)->count())->toBe(1);
    Notification::assertSentOnDemandTimes(BookingReminderNotification::class, 1);
});

it('sends the second reminder in its own window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-07 10:00:00', 'UTC'));
    $this->artisan('bookings:send-reminders');

    $this->travelTo(CarbonImmutable::parse('2026-06-09 09:00:00', 'UTC'));
    $this->artisan('bookings:send-reminders');

    expect(BookingReminder::query()->where('booking_id', $this->booking->id)->pluck('type')->all())
        ->toContain('reminder_second');
});

it('does not send a disabled reminder', function () {
    BookingSetting::forTeam($this->team)->update(['reminder_first_enabled' => false]);

    $this->travelTo(CarbonImmutable::parse('2026-06-07 10:00:00', 'UTC'));
    $this->artisan('bookings:send-reminders');

    expect(BookingReminder::query()->where('type', 'reminder_first')->exists())->toBeFalse();
});

it('does not send reminders for cancelled bookings', function () {
    $this->booking->update(['status' => BookingStatus::Cancelled]);

    $this->travelTo(CarbonImmutable::parse('2026-06-07 10:00:00', 'UTC'));
    $this->artisan('bookings:send-reminders');

    expect(BookingReminder::query()->count())->toBe(0);
});

it('does not send a late reminder for a booking created inside the window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-09 09:00:00', 'UTC'));

    Booking::factory()->for($this->team)->forProduct($this->product)->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => CarbonImmutable::parse('2026-06-10 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-10 10:00', 'Europe/Madrid'),
        'customer_email' => 'last@example.com',
        'created_at' => now(),
    ]);

    $this->artisan('bookings:send-reminders');

    expect(BookingReminder::query()->where('booking_id', '!=', $this->booking->id)->count())->toBe(0);
});
