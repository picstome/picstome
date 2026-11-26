<?php

use App\Enums\BookingStatus;
use App\Enums\DepositType;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\AvailabilityWindow;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingPendingBizum;
use App\Notifications\BookingPendingBizumForPhotographer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

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
        'slot_step_minutes' => 30,
        'buffer_minutes' => 15,
        'card_enabled' => true,
        'bizum_enabled' => false,
        'cancellation_policy' => 'The deposit is not refundable.',
    ]);

    AvailabilityWindow::factory()
        ->for($this->team)
        ->forWeekday(1, '09:00:00', '13:00:00')
        ->create();

    $this->product = Product::factory()->for($this->team)->create([
        'name' => 'Sunset session',
        'duration_minutes' => 60,
        'price' => 20000,
        'deposit_type' => DepositType::Fixed,
        'deposit_value' => 5000,
    ]);
});

it('shows the bookable products on the public profile when the public flag is on', function () {
    config(['picstome.bookings_public_profile' => true]);

    get('/@'.$this->team->handle)
        ->assertSuccessful()
        ->assertSee('Book a session')
        ->assertSee(route('handle.products', ['handle' => $this->team->handle]), false);
});

it('hides the booking section from the biolink by default', function () {
    get('/@'.$this->team->handle)
        ->assertSuccessful()
        ->assertDontSee('Book a session');
});

it('opens the public booking page', function () {
    Livewire::test('pages::handle.book', ['handle' => $this->team->handle])
        ->assertSet('step', 'datetime')
        ->assertSee('Sunset session')
        ->assertSee('09:00');
});

it('opens the public booking page from a product link', function () {
    get('/@'.$this->team->handle.'/book/'.$this->product->ulid)
        ->assertSuccessful()
        ->assertSee('Sunset session');
});

it('changes month on the first click', function () {
    Livewire::test('pages::handle.book', ['handle' => $this->team->handle])
        ->assertSee('June 2026')
        ->call('nextMonth')
        ->assertSee('July 2026')
        ->call('nextMonth')
        ->assertSee('August 2026');
});

it('is not available when the feature is disabled', function () {
    config(['picstome.bookings_feature_enabled' => false]);

    get('/@'.$this->team->handle.'/book')->assertNotFound();
});

it('is not available for a team whose owner cannot see the feature', function () {
    config(['picstome.admin_emails' => []]);

    get('/@'.$this->team->handle.'/book')->assertNotFound();
});

it('lets the client choose the deposit and pays it with a card in fake mode', function () {
    Notification::fake();

    Livewire::test('pages::handle.book', ['handle' => $this->team->handle])
        ->call('selectDate', '2026-06-01')
        ->call('selectSlot', '09:00')
        ->call('continueToDetails')
        ->assertSet('step', 'details')
        ->set('form.name', 'Ana Torres')
        ->set('form.email', 'ana@example.com')
        ->set('form.phone', '+34 600 000 000')
        ->set('form.acceptedPolicy', true)
        ->call('submitDetails')
        ->assertSet('step', 'payment')
        ->call('setPaymentKind', 'deposit')
        ->call('payWithCard');

    $booking = Booking::query()->sole();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payment_kind)->toBe(PaymentKind::Deposit)
        ->and($booking->customer_name)->toBe('Ana Torres')
        ->and($booking->policy_snapshot)->toBe('The deposit is not refundable.')
        ->and($booking->photoshoot_id)->not->toBeNull();

    expect(Payment::query()->where('booking_id', $booking->id)->sole()->amount)->toBe(5000)
        ->and(Customer::query()->where('email', 'ana@example.com')->exists())->toBeTrue();

    Notification::assertSentOnDemand(BookingConfirmed::class);
});

it('requires the phone number when the photographer enabled it', function () {
    BookingSetting::forTeam($this->team)->update(['phone_required' => true]);

    Livewire::test('pages::handle.book', ['handle' => $this->team->handle])
        ->call('selectDate', '2026-06-01')
        ->call('selectSlot', '09:00')
        ->call('continueToDetails')
        ->set('form.name', 'Ana Torres')
        ->set('form.email', 'ana@example.com')
        ->set('form.acceptedPolicy', true)
        ->call('submitDetails')
        ->assertHasErrors('form.phone');
});

it('keeps the booking pending when only Bizum is available', function () {
    Notification::fake();

    BookingSetting::forTeam($this->team)->update([
        'card_enabled' => false,
        'bizum_enabled' => true,
        'bizum_phone' => '+34 600 000 000',
    ]);

    Livewire::test('pages::handle.book', ['handle' => $this->team->handle])
        ->call('selectDate', '2026-06-01')
        ->call('selectSlot', '09:00')
        ->call('continueToDetails')
        ->set('form.name', 'Ana Torres')
        ->set('form.email', 'ana@example.com')
        ->set('form.acceptedPolicy', true)
        ->call('submitDetails')
        ->assertSet('step', 'payment')
        ->call('setPaymentKind', 'deposit')
        ->call('payWithBizum')
        ->assertSet('step', 'bizum')
        ->assertSee('+34 600 000 000');

    $booking = Booking::query()->sole();

    expect($booking->status)->toBe(BookingStatus::PendingBizum)
        ->and($booking->payment_method)->toBe(PaymentMethod::Bizum);

    Notification::assertSentOnDemand(BookingPendingBizum::class);
    Notification::assertSentTo($this->user, BookingPendingBizumForPhotographer::class);
});
