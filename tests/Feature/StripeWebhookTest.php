<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));

    Notification::fake();

    config(['services.stripe.connect_webhook_secret' => 'whsec_connect_test']);

    $this->team = Team::factory()->create();

    $this->booking = Booking::factory()->for($this->team)->pending()->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
        'total_amount' => 20000,
        'deposit_amount' => 5000,
        'balance_amount' => 15000,
        'customer_email' => 'ana@example.com',
    ]);
});

function stripePayload(Booking $booking, string $eventId = 'evt_test_1', string $kind = 'deposit'): string
{
    return json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'account' => 'acct_connected_1',
        'data' => [
            'object' => [
                'id' => 'cs_test_1',
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'amount_total' => 5000,
                'payment_intent' => 'pi_test_1',
                'customer_details' => ['email' => 'ana@example.com'],
                'metadata' => [
                    'booking_ulid' => $booking->ulid,
                    'payment_kind' => $kind,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

function stripeSignature(string $payload, string $secret = 'whsec_connect_test'): string
{
    $timestamp = time();

    return sprintf(
        't=%d,v1=%s',
        $timestamp,
        hash_hmac('sha256', $timestamp.'.'.$payload, $secret),
    );
}

it('confirms the booking when Stripe reports the payment', function () {
    $payload = stripePayload($this->booking);
    $signature = stripeSignature($payload);

    $this->call('POST', '/stripe/connect/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertSuccessful();

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payment_kind)->toBe(PaymentKind::Deposit)
        ->and($booking->photoshoot_id)->not->toBeNull();

    $payment = Payment::query()->where('booking_id', $booking->id)->sole();

    expect($payment->amount)->toBe(5000)
        ->and($payment->stripe_payment_intent_id)->toBe('pi_test_1')
        ->and($payment->payment_kind)->toBe(PaymentKind::Deposit);

    expect(StripeEvent::query()->where('stripe_event_id', 'evt_test_1')->exists())->toBeTrue();
});

it('handles the same event twice without duplicating the payment', function () {
    $payload = stripePayload($this->booking);
    $signature = stripeSignature($payload);

    $server = [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', '/stripe/connect/webhook', [], [], [], $server, $payload)->assertSuccessful();
    $this->call('POST', '/stripe/connect/webhook', [], [], [], $server, $payload)->assertSuccessful();

    expect(Payment::query()->where('booking_id', $this->booking->id)->count())->toBe(1);
});

it('rejects a webhook with an invalid signature', function () {
    $payload = stripePayload($this->booking);

    $this->call('POST', '/stripe/connect/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and(Payment::query()->count())->toBe(0);
});

it('keeps the Cashier platform webhook on its own route', function () {
    expect(route('cashier.webhook'))->toBe(url('/stripe/webhook'))
        ->and(route('webhooks.stripe.connect'))->toBe(url('/stripe/connect/webhook'));
});

it('rejects events that did not originate in a connected account', function () {
    $payload = stripePayload($this->booking);
    $decoded = json_decode($payload, true);
    unset($decoded['account']);
    $payload = json_encode($decoded, JSON_THROW_ON_ERROR);

    $this->call('POST', '/stripe/connect/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::PendingPayment);
});

it('fails when the Connect webhook secret is not configured', function () {
    config(['services.stripe.connect_webhook_secret' => null]);

    $payload = stripePayload($this->booking);

    $this->call('POST', '/stripe/connect/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(500);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::PendingPayment);
});
