<?php

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingCreated;
use App\Notifications\PaymentLinkBizumPending;
use App\Notifications\PaymentLinkBizumPendingForPhotographer;
use App\Services\PaymentLinkService;
use Facades\App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('picstome.disk'));

    Notification::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->markOnboarded();
});

it('offers Bizum even when the booking feature flags are disabled', function () {
    config([
        'picstome.bookings_feature_enabled' => false,
        'picstome.bookings_feature_admin_only' => true,
    ]);

    $this->team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
        'bizum_instructions' => 'Send the Bizum and wait for confirmation.',
    ]);

    Livewire::test('pages::pay.show', ['handle' => $this->team->handle, 'amount' => 100, 'description' => 'Wedding session'])
        ->assertSee('Pay with Bizum')
        ->assertSee('Pay by card');
});

it('shows the session date and time on the payment link page', function () {
    $this->team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
    ]);

    Livewire::withQueryParams([
        'booking' => true,
        'booking_date' => '2026-11-26',
        'booking_start_time' => '15:00',
        'booking_end_time' => '16:00',
        'timezone' => 'Europe/Madrid',
    ])
        ->test('pages::pay.show', [
            'handle' => $this->team->handle,
            'amount' => 120,
            'description' => 'Sesión Bea',
        ])
        ->assertSee('Sesión Bea')
        ->assertSee('November 26, 2026')
        ->assertSee('15:00 - 16:00');
});

it('creates a pending Bizum payment and notifies the photographer', function () {
    $this->team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
    ]);

    $component = Livewire::test('pages::pay.show', ['handle' => $this->team->handle, 'amount' => 100, 'description' => 'Wedding session'])
        ->call('showBizumForm')
        ->set('name', 'Ana Torres')
        ->set('email', 'ana@example.com')
        ->set('phone', '611222333')
        ->call('payWithBizum');

    $payment = $this->team->payments()->sole();

    expect($payment->status)->toBe(PaymentStatus::PendingBizum)
        ->and($payment->payment_method->value)->toBe('bizum')
        ->and($payment->reference)->toMatch('/^Ana-\d+$/')
        ->and($payment->amount)->toBe(10000)
        ->and($payment->customer_name)->toBe('Ana Torres')
        ->and($payment->customer_email)->toBe('ana@example.com')
        ->and($payment->completed_at)->toBeNull();

    $component->assertRedirect(route('handle.pay.request', [
        'handle' => $this->team->handle,
        'reference' => $payment->reference,
    ]));

    Notification::assertSentTo($this->user, PaymentLinkBizumPendingForPhotographer::class);

    Notification::assertSentOnDemand(
        PaymentLinkBizumPending::class,
        fn (PaymentLinkBizumPending $notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ana@example.com'
            && $notification->payment->is($payment),
    );
});

it('shows the instructions to the client', function () {
    $payment = Payment::factory()->for($this->team)->pendingBizum()->create([
        'amount' => 10000,
        'currency' => 'eur',
        'description' => 'Wedding session',
        'customer_email' => 'ana@example.com',
        'metadata' => [
            'booking' => true,
            'booking_date' => '2026-11-26',
            'booking_start_time' => '15:00',
            'booking_end_time' => '16:00',
            'timezone' => 'Europe/Madrid',
        ],
    ]);

    $this->team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
        'bizum_instructions' => 'Send the Bizum and wait for confirmation.',
    ]);

    Livewire::test('pages::pay.request', [
        'handle' => $this->team->handle,
        'reference' => $payment->reference,
    ])
        ->assertSee($payment->reference)
        ->assertSee('Send the Bizum to')
        ->assertSee('November 26, 2026')
        ->assertSee('15:00 - 16:00')
        ->assertSee('600123123')
        ->assertSee('Send the Bizum and wait for confirmation.');
});

it('confirms a pending Bizum, creates the invoice and notifies the client', function () {
    $payment = Payment::factory()->for($this->team)->pendingBizum()->create([
        'amount' => 10000,
        'currency' => 'eur',
        'description' => 'Wedding session',
        'customer_name' => 'Ana Torres',
        'customer_email' => 'ana@example.com',
        'metadata' => [
            'booking' => true,
            'booking_date' => '2026-12-26',
            'booking_start_time' => '15:00',
            'booking_end_time' => '16:00',
            'timezone' => 'Europe/Madrid',
        ],
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->call('confirmBizum', $payment->id)
        ->assertHasNoErrors();

    $payment->refresh();

    $component->assertRedirect(route('invoices.show', [
        'invoice' => $payment->invoice,
        'created' => 1,
    ]));

    expect($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->completed_at)->not->toBeNull()
        ->and($payment->invoice)->not->toBeNull()
        ->and($this->team->customers()->where('email', 'ana@example.com')->exists())->toBeTrue();

    expect($payment->invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($payment->invoice->isPaid())->toBeTrue();

    $booking = $this->team->bookings()->sole();

    expect($booking->status->value)->toBe('confirmed')
        ->and($booking->source)->toBe('payment_link')
        ->and($booking->photoshoot_id)->not->toBeNull()
        ->and($booking->starts_at->setTimezone('Europe/Madrid')->toDateTimeString())->toBe('2026-12-26 15:00:00')
        ->and($booking->ends_at->setTimezone('Europe/Madrid')->toDateTimeString())->toBe('2026-12-26 16:00:00')
        ->and($payment->fresh()->booking_id)->toBe($booking->id);

    Livewire::withQueryParams(['view' => 'agenda', 'month' => '2026-12', 'selectedDate' => '2026-12-26'])
        ->actingAs($this->user)
        ->test('pages::bookings.index')
        ->assertSee('Wedding session');

    Notification::assertSentTo($this->user, BookingCreated::class);
    Notification::assertSentOnDemand(
        BookingCreated::class,
        fn (BookingCreated $notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ana@example.com'
            && $notification->payment->is($payment),
    );
});

it('cancels a pending Bizum payment', function () {
    $payment = Payment::factory()->for($this->team)->pendingBizum()->create();

    Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->call('cancelBizum', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->fresh()->completed_at)->toBeNull();
});

it('creates a draft invoice for a card payment link', function () {
    $mockSession = [
        'metadata' => [
            'description' => 'Wedding session',
            'booking' => true,
            'booking_date' => '2026-12-26',
            'booking_start_time' => '15:00',
            'booking_end_time' => '16:00',
            'timezone' => 'Europe/Madrid',
        ],
        'line_items' => [
            'data' => [
                ['description' => 'Wedding session'],
            ],
        ],
        'payment_status' => 'paid',
        'payment_intent' => 'pi_card_invoice',
        'amount_total' => 10000,
        'currency' => 'eur',
        'customer_details' => [
            'email' => 'ana@example.com',
            'name' => 'Ana Torres',
        ],
    ];

    StripeConnectService::shouldReceive('getCheckoutSession')
        ->once()
        ->andReturn($mockSession);

    Livewire::test('pages::pay.success', [
        'handle' => $this->team->handle,
        'session_id' => 'sess_card_invoice',
    ])->assertOk();

    $payment = $this->team->payments()->sole();

    expect($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->invoice)->not->toBeNull()
        ->and($payment->invoice->isPaid())->toBeTrue()
        ->and($this->team->bookings()->where('source', 'payment_link')->exists())->toBeTrue();
});

it('does not mix requests from another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;
    $payment = Payment::factory()->for($otherTeam)->pendingBizum()->create();

    $this->get('/@'.$this->team->handle.'/pay/request/'.$payment->reference)
        ->assertNotFound();
});

it('expires pending Bizum requests after their window', function () {
    $payment = Payment::factory()->for($this->team)->pendingBizum()->create([
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(PaymentLinkService::class)->expirePendingBizum())->toBe(1)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Expired);
});

it('accepts Bizum without Stripe onboarding', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
    ]);

    Livewire::test('pages::pay.show', [
        'handle' => $team->handle,
        'amount' => 100,
        'description' => 'Wedding session',
    ])
        ->assertSee('Pay with Bizum')
        ->assertDontSee('Pay by card');

    Livewire::actingAs($user)
        ->test('pages::payments')
        ->assertSee('Generate Payment Link');
});

it('requires the phone number when the team enabled the shared option', function () {
    $this->team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
        'phone_required' => true,
    ]);

    Livewire::test('pages::pay.show', [
        'handle' => $this->team->handle,
        'amount' => 100,
        'description' => 'Wedding session',
    ])
        ->call('showBizumForm')
        ->set('name', 'Ana Torres')
        ->set('email', 'ana@example.com')
        ->call('payWithBizum')
        ->assertHasErrors('phone');

    expect($this->team->payments()->count())->toBe(0);
});
