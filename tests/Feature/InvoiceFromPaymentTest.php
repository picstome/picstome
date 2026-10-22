<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceFromPaymentService;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 10]);

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->service = app(InvoiceFromPaymentService::class);
});

it('creates a draft invoice from a payment', function () {
    $payment = Payment::factory()->for($this->team)->create([
        'amount' => 15000,
        'description' => 'Photo session',
        'currency' => 'eur',
    ]);

    $invoice = $this->service->createFromPayment($payment, $this->user);

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->number)->toBeNull()
        ->and($invoice->fingerprint)->toBeNull()
        ->and($invoice->pdf_path)->toBeNull()
        ->and((float) $invoice->total)->toBe(150.0)
        ->and((float) $invoice->vat_amount)->toBe(26.03)
        ->and((float) $invoice->base_amount)->toBe(123.97)
        ->and($invoice->currency)->toBe('EUR')
        ->and($invoice->invoice_data['line_items'][0]['description'])->toBe('Photo session')
        ->and((float) $invoice->invoice_data['line_items'][0]['unit_price'])->toBe(123.97);
});

it('links the payment with the invoice', function () {
    $payment = Payment::factory()->for($this->team)->create();

    $invoice = $this->service->createFromPayment($payment, $this->user);

    expect($payment->fresh()->invoice_id)->toBe($invoice->id)
        ->and($invoice->payments)->toHaveCount(1);
});

it('records the payment in the audit trail', function () {
    $payment = Payment::factory()->for($this->team)->create(['amount' => 15000]);

    $invoice = $this->service->createFromPayment($payment, $this->user);

    $event = $invoice->events()->where('event', InvoiceEvent::CREATED)->first();

    expect($event)->not->toBeNull()
        ->and($event->user_id)->toBe($this->user->id)
        ->and($event->context['payment_id'])->toBe($payment->id)
        ->and($event->context['payment_amount'])->toBe(15000);
});

it('uses a simplified invoice when the client has no fiscal data', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'legal_name' => null,
        'tax_id' => null,
    ]);

    $payment = Payment::factory()->for($this->team)->create([
        'customer_email' => $customer->email,
    ]);

    $invoice = $this->service->createFromPayment($payment, $this->user);

    expect($invoice->invoice_type)->toBe(Invoice::TYPE_SIMPLIFIED)
        ->and($invoice->customer_id)->toBe($customer->id);
});

it('uses a complete invoice when the client has fiscal data', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Acme Studio SL',
        'email' => 'acme@example.com',
        'legal_name' => 'Acme Studio SL',
        'tax_id' => 'B12345678',
        'address' => 'Calle Mayor 1',
        'city' => 'Madrid',
        'postal_code' => '28001',
        'country' => 'ES',
    ]);

    $payment = Payment::factory()->for($this->team)->create([
        'customer_email' => $customer->email,
    ]);

    $invoice = $this->service->createFromPayment($payment, $this->user);

    expect($invoice->invoice_type)->toBe(Invoice::TYPE_FULL)
        ->and($invoice->customer_id)->toBe($customer->id);
});

it('creates the invoice without a client when the payment has no email', function () {
    $payment = Payment::factory()->for($this->team)->create([
        'customer_email' => null,
    ]);

    $invoice = $this->service->createFromPayment($payment, $this->user);

    expect($invoice->customer_id)->toBeNull()
        ->and($invoice->invoice_type)->toBe(Invoice::TYPE_SIMPLIFIED);
});

it('does not invoice the same payment twice', function () {
    $payment = Payment::factory()->for($this->team)->create();

    $this->service->createFromPayment($payment, $this->user);

    expect(fn () => $this->service->createFromPayment($payment->fresh(), $this->user))
        ->toThrow(ValidationException::class, 'This payment is already invoiced');
});

it('issues the invoice created from a payment', function () {
    $payment = Payment::factory()->for($this->team)->create([
        'amount' => 15000,
        'currency' => 'eur',
    ]);

    $invoice = $this->service->createFromPayment($payment, $this->user);

    $issued = app(InvoiceIssuanceService::class)->issue($invoice, $this->user);

    expect($issued->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($issued->number)->toBe(1)
        ->and($issued->pdf_path)->not->toBeNull()
        ->and((float) $issued->total)->toBe(150.0)
        ->and((float) $issued->amount_due)->toBe(150.0);
});

it('keeps the payment of another team out of reach', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    InvoiceSetting::factory()->for($otherTeam)->create();

    $payment = Payment::factory()->for($otherTeam)->create();

    $invoice = $this->service->createFromPayment($payment, $this->user);

    expect($invoice->team_id)->toBe($otherTeam->id)
        ->and($this->team->invoices()->count())->toBe(0);
});
