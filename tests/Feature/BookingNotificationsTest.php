<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\AvailabilityWindow;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Photoshoot;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingCancelledForPhotographer;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingPaid;
use App\Notifications\BookingPendingBizum;
use App\Notifications\BookingPendingBizumForPhotographer;
use App\Services\Booking\BookingFulfilmentService;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));

    Notification::fake();

    Storage::fake(config('picstome.disk'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    BookingSetting::forTeam($this->team)->update([
        'timezone' => 'Europe/Madrid',
        'min_notice_hours' => 0,
    ]);

    AvailabilityWindow::factory()
        ->for($this->team)
        ->forWeekday(1, '09:00:00', '13:00:00')
        ->create();

    $this->product = Product::factory()->for($this->team)->create([
        'name' => 'Sunset session',
        'price' => 20000,
    ]);

    $this->booking = Booking::factory()->for($this->team)->pending()->forProduct($this->product)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
        'customer_email' => 'ana@example.com',
        'deposit_amount' => 5000,
        'balance_amount' => 15000,
    ]);
});

it('notifies the client and the photographer when a booking is paid', function () {
    app(BookingFulfilmentService::class)->pay(
        $this->booking,
        PaymentKind::Full,
        PaymentMethod::Cash,
    );

    Notification::assertSentOnDemand(BookingConfirmed::class);
    Notification::assertSentTo($this->user, BookingPaid::class);
});

it('notifies the client and the photographer when a booking is cancelled', function () {
    app(BookingService::class)->cancel($this->booking, 'Bad weather');

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($this->booking->fresh()->cancellation_reason)->toBe('Bad weather');

    Notification::assertSentOnDemand(BookingCancelled::class);
    Notification::assertSentTo($this->user, BookingCancelledForPhotographer::class);
});

it('does not notify twice when an already cancelled booking is cancelled again', function () {
    $service = app(BookingService::class);

    $service->cancel($this->booking);
    $service->cancel($this->booking->fresh());

    Notification::assertSentOnDemandTimes(BookingCancelled::class, 1);
    Notification::assertSentToTimes($this->user, BookingCancelledForPhotographer::class, 1);
});

it('keeps teams isolated in the notification assertions', function () {
    $otherTeam = Team::factory()->create();

    app(BookingService::class)->cancel($this->booking);

    Notification::assertNotSentTo($otherTeam->owner, BookingCancelledForPhotographer::class);
});

it('deletes the photoshoot of a cancelled booking when it has no client material', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();
    $this->booking->update(['photoshoot_id' => $photoshoot->id]);

    app(BookingService::class)->cancel($this->booking);

    expect(Photoshoot::find($photoshoot->id))->toBeNull()
        ->and($this->booking->fresh()->photoshoot_id)->toBeNull();
});

it('keeps the photoshoot when it already has a gallery', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();
    Gallery::factory()->for($photoshoot)->create();
    $this->booking->update(['photoshoot_id' => $photoshoot->id]);

    app(BookingService::class)->cancel($this->booking);

    expect(Photoshoot::find($photoshoot->id))->not->toBeNull();
});

it('links the photographer email to the draft invoice', function () {
    app(BookingFulfilmentService::class)->pay(
        $this->booking,
        PaymentKind::Full,
        PaymentMethod::Cash,
    );

    $payment = $this->booking->payments()->where('payment_kind', 'full')->sole();
    $mail = (new BookingPaid($this->booking->fresh()))->toMail($this->user);

    expect($payment->invoice)->not->toBeNull()
        ->and($mail->actionUrl)->toBe(route('invoices.show', ['invoice' => $payment->invoice]));
});

it('notifies the photographer when a booking is left pending by Bizum', function () {
    app(BookingFulfilmentService::class)->markPendingBizum($this->booking, PaymentKind::Deposit);

    Notification::assertSentOnDemand(BookingPendingBizum::class);
    Notification::assertSentTo($this->user, BookingPendingBizumForPhotographer::class);
});

it('keeps the customer card and the invoice in sync with the booking name', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Old Name',
        'email' => 'new@example.com',
    ]);

    $booking = app(BookingService::class)->hold(
        $this->team,
        $this->product,
        CarbonImmutable::parse('2026-06-01 11:00', 'Europe/Madrid'),
        ['name' => 'New Name', 'email' => 'new@example.com'],
        PaymentMethod::Cash,
    );

    expect($customer->fresh()->name)->toBe('New Name')
        ->and($booking->customer_id)->toBe($customer->id);

    app(BookingFulfilmentService::class)->pay($booking, PaymentKind::Full, PaymentMethod::Cash);

    $payment = $booking->payments()->where('payment_kind', 'full')->sole();
    $invoice = $payment->invoice;

    expect($invoice)->not->toBeNull()
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($invoice->customer_id)->toBe($customer->id)
        ->and($invoice->invoice_data['customer']['name'])->toBe('New Name')
        ->and($invoice->total)->toEqual('200.00');
});

it('issues the invoice automatically when the team enabled it and has fiscal data', function () {
    InvoiceSetting::factory()->for($this->team)->create();
    BookingSetting::forTeam($this->team)->update(['auto_issue_invoices' => true]);

    app(BookingFulfilmentService::class)->pay($this->booking, PaymentKind::Full, PaymentMethod::Cash);

    $invoice = $this->booking->payments()->where('payment_kind', 'full')->sole()->invoice;

    expect($invoice->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($invoice->number)->not->toBeNull()
        ->and($invoice->paid_at)->not->toBeNull();
});

it('keeps the invoice as a draft when auto issue is on but fiscal data is missing', function () {
    InvoiceSetting::factory()->for($this->team)->create([
        'legal_name' => null,
        'tax_id' => null,
        'address' => null,
        'city' => null,
        'postal_code' => null,
    ]);
    BookingSetting::forTeam($this->team)->update(['auto_issue_invoices' => true]);

    app(BookingFulfilmentService::class)->pay($this->booking, PaymentKind::Full, PaymentMethod::Cash);

    $invoice = $this->booking->payments()->where('payment_kind', 'full')->sole()->invoice;

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->number)->toBeNull();
});
