<?php

use App\Models\Photoshoot;
use App\Models\User;
use App\Notifications\BookingCreated;
use Facades\App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->markOnboarded();
});

it('creates a photoshoot if booking is enabled and no photoshoot_id is present', function () {
    Notification::fake();
    $mockSession = [
        'metadata' => [
            'booking' => true,
            'booking_date' => '2025-10-21',
            'booking_start_time' => '14:00',
            'booking_end_time' => '16:00',
            'timezone' => 'Europe/Berlin',
        ],
        'line_items' => [
            'data' => [
                ['description' => 'Test Session'],
            ],
        ],
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_123',
        'amount_total' => 10000,
        'currency' => 'eur',
        'customer_details' => [
            'email' => 'client@example.com',
            'name' => 'John Doe',
        ],
    ];

    StripeConnectService::shouldReceive('getCheckoutSession')
        ->once()
        ->andReturn($mockSession);

    Volt::test('pages.pay.success', ['handle' => $this->team->handle, 'session_id' => 'sess_123'])
        ->assertOk();

    $customer = $this->team->customers()->first();
    expect($customer)->not->toBeNull();
    expect($customer->email)->toBe('client@example.com');
    expect($customer->name)->toBe('John Doe');

    $photoshoot = $this->team->photoshoots()->first();
    expect($photoshoot)->not->toBeNull();
    expect($photoshoot->name)->toContain('Test Session');
    expect($photoshoot->date->toDateString())->toBe('2025-10-21');
    expect($photoshoot->customer_id)->toBe($customer->id);

    $payment = $this->team->payments()->first();
    expect($payment)->not->toBeNull();
    expect($payment->photoshoot_id)->toBe($photoshoot->id);

    // Assert notification sent to team owner
    Notification::assertSentTo(
        $this->user,
        BookingCreated::class,
        function (BookingCreated $notification, $notifiable) use ($photoshoot) {
            return $notification->photoshoot->is($photoshoot);
        }
    );
    // Assert notification sent to payer email (on-demand)
    Notification::assertSentOnDemand(
        BookingCreated::class,
        function (BookingCreated $notification, $channels, $notifiable) use ($photoshoot) {
            return $notifiable->routes['mail'] === 'client@example.com'
                && $notification->photoshoot->is($photoshoot);
        }
    );
});

it('does not create a photoshoot if booking is not enabled', function () {
    $mockSession = [
        'metadata' => [
            // booking not set or false
        ],
        'line_items' => [
            'data' => [
                ['description' => 'Test Session'],
            ],
        ],
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_456',
        'amount_total' => 10000,
        'currency' => 'eur',
        'customer_details' => [
            'email' => 'client2@example.com',
            'name' => 'Jane Doe',
        ],
    ];

    StripeConnectService::shouldReceive('getCheckoutSession')
        ->once()
        ->andReturn($mockSession);

    Volt::test('pages.pay.success', ['handle' => $this->team->handle, 'session_id' => 'sess_456'])
        ->assertOk();

    $customer = $this->team->customers()->first();
    expect($customer)->not->toBeNull();
    expect($customer->email)->toBe('client2@example.com');
    expect($customer->name)->toBe('Jane Doe');

    expect($this->team->photoshoots()->count())->toBe(0);
    $payment = $this->team->payments()->first();
    expect($payment)->not->toBeNull();
    expect($payment->photoshoot_id)->toBeNull();
});

it('does not create a photoshoot if photoshoot_id is present', function () {
    $existingPhotoshoot = Photoshoot::factory()->for($this->team)->create();
    $mockSession = [
        'metadata' => [
            'booking' => true,
            'photoshoot_id' => $existingPhotoshoot->id,
            'booking_date' => '2025-10-21',
            'booking_start_time' => '14:00',
            'booking_end_time' => '16:00',
            'timezone' => 'Europe/Berlin',
        ],
        'line_items' => [
            'data' => [
                ['description' => 'Test Session'],
            ],
        ],
        'payment_status' => 'paid',
        'payment_intent' => 'pi_test_789',
        'amount_total' => 10000,
        'currency' => 'eur',
        'customer_details' => [
            'email' => 'client3@example.com',
            'name' => 'Bob Smith',
        ],
    ];

    StripeConnectService::shouldReceive('getCheckoutSession')
        ->once()
        ->andReturn($mockSession);

    Volt::test('pages.pay.success', ['handle' => $this->team->handle, 'session_id' => 'sess_789'])
        ->assertOk();

    $customer = $this->team->customers()->first();
    expect($customer)->not->toBeNull();
    expect($customer->email)->toBe('client3@example.com');
    expect($customer->name)->toBe('Bob Smith');

    // Only the existing photoshoot should exist
    expect($this->team->photoshoots()->count())->toBe(1);
    $payment = $this->team->payments()->first();
    expect($payment)->not->toBeNull();
    expect($payment->photoshoot_id)->toBe($existingPhotoshoot->id);
});
