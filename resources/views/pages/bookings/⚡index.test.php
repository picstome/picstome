<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\AvailabilityWindow;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => [$this->user->email],
    ]);

    BookingSetting::forTeam($this->team)->update([
        'timezone' => 'Europe/Madrid',
        'min_notice_hours' => 0,
        'slot_step_minutes' => 30,
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
});

it('is hidden from users who are not administrators', function () {
    config(['picstome.admin_emails' => []]);

    actingAs($this->user)->get('/bookings')->assertRedirect(route('bookings.coming-soon'));
});

it('shows the bookings of the visible month', function () {
    $booking = Booking::factory()->for($this->team)->create([
        'product_name' => 'Sunset session',
        'customer_name' => 'Ana Torres',
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);

    actingAs($this->user)->get('/bookings')->assertSuccessful();

    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->assertSee('Ana Torres')
        ->assertSee('Sunset session')
        ->assertSet('selectedDate', '2026-06-01');
});

it('changes month on the first click', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->assertSee('June 2026')
        ->call('nextMonth')
        ->assertSee('July 2026');
});

it('shows the products tab', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('setView', 'products')
        ->assertSet('view', 'products')
        ->assertSee($this->product->name);
});

it('opens the agenda view from the query string', function () {
    actingAs($this->user)
        ->get('/bookings?view=agenda')
        ->assertSuccessful()
        ->assertSee('Next sessions');
});

it('creates a manual booking from the calendar', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('openManualModal', '2026-06-01')
        ->set('manualForm.product_id', $this->product->id)
        ->set('manualForm.date', '2026-06-01')
        ->set('manualForm.start_time', '09:00')
        ->set('manualForm.name', 'Luis Ramos')
        ->set('manualForm.email', 'luis@example.com')
        ->call('saveManualBooking')
        ->assertHasNoErrors();

    $booking = Booking::query()->sole();

    expect($booking->customer_name)->toBe('Luis Ramos')
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->source)->toBe('manual')
        ->and($booking->payment_method)->toBe(PaymentMethod::Cash)
        ->and($booking->total_amount)->toBe(20000)
        ->and($booking->customer_id)->not->toBeNull()
        ->and(Customer::query()->where('email', 'luis@example.com')->exists())->toBeTrue();
});

it('reuses an existing customer picked with the search', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Ana Torres',
        'email' => 'ana@example.com',
        'phone' => '+34 600 000 000',
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('openManualModal', '2026-06-01')
        ->set('customerSearch', 'Ana')
        ->assertSee('Ana Torres')
        ->call('selectCustomer', $customer->id)
        ->assertSet('manualForm.name', 'Ana Torres')
        ->assertSet('manualForm.email', 'ana@example.com')
        ->set('manualForm.product_id', $this->product->id)
        ->set('manualForm.date', '2026-06-01')
        ->set('manualForm.start_time', '09:00')
        ->call('saveManualBooking')
        ->assertHasNoErrors();

    expect($this->team->customers()->count())->toBe(1)
        ->and(Booking::query()->sole()->customer_id)->toBe($customer->id);
});

it('rejects a manual booking on a taken slot', function () {
    Booking::factory()->for($this->team)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('openManualModal', '2026-06-01')
        ->set('manualForm.product_id', $this->product->id)
        ->set('manualForm.date', '2026-06-01')
        ->set('manualForm.start_time', '09:30')
        ->set('manualForm.name', 'Luis Ramos')
        ->set('manualForm.email', 'luis@example.com')
        ->call('saveManualBooking')
        ->assertHasErrors('manualForm.start_time');

    expect(Booking::query()->count())->toBe(1);
});

it('blocks a day and closes its slots', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('openBlockModal', '2026-06-05')
        ->set('blockForm.starts_on', '2026-06-05')
        ->set('blockForm.ends_on', '2026-06-05')
        ->set('blockForm.all_day', true)
        ->set('blockForm.reason', 'Holiday')
        ->call('saveBlock')
        ->assertHasNoErrors();

    expect($this->team->calendarBlocks()->count())->toBe(1);

    $slots = app(AvailabilityService::class)->slotsForDate(
        $this->team->fresh(),
        $this->product,
        CarbonImmutable::parse('2026-06-05', 'Europe/Madrid'),
    );

    expect($slots)->toBeEmpty();
});

it('blocks a multi-day partial range', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('openBlockModal', '2026-06-05')
        ->set('blockForm.starts_on', '2026-06-05')
        ->set('blockForm.ends_on', '2026-06-08')
        ->set('blockForm.all_day', false)
        ->set('blockForm.start_time', '20:00')
        ->set('blockForm.end_time', '18:00')
        ->call('saveBlock')
        ->assertHasNoErrors();

    expect($this->team->calendarBlocks()->count())->toBe(1);

    $slots = app(AvailabilityService::class)->slotsForDate(
        $this->team->fresh(),
        $this->product,
        CarbonImmutable::parse('2026-06-08', 'Europe/Madrid'),
    );

    expect($slots)->toBeEmpty();
});

it('rejects a partial block whose end is before its start on the same day', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('openBlockModal', '2026-06-05')
        ->set('blockForm.starts_on', '2026-06-05')
        ->set('blockForm.ends_on', '2026-06-05')
        ->set('blockForm.all_day', false)
        ->set('blockForm.start_time', '20:00')
        ->set('blockForm.end_time', '18:00')
        ->call('saveBlock')
        ->assertHasErrors('blockForm.end_time');

    expect($this->team->calendarBlocks()->count())->toBe(0);
});

it('confirms a pending Bizum booking', function () {
    $booking = Booking::factory()->for($this->team)->pending(BookingStatus::PendingBizum)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('confirmBooking', $booking->id);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payment_method)->toBe(PaymentMethod::Bizum);

    $payment = $booking->payments()->sole();

    expect($payment->invoice)->not->toBeNull();

    $component->assertRedirect(route('invoices.show', [
        'invoice' => $payment->invoice,
        'created' => 1,
    ]));
});

it('cancels a booking after confirmation', function () {
    $booking = Booking::factory()->for($this->team)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::bookings.index')
        ->call('requestCancel', $booking->id)
        ->call('cancelBooking');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});
