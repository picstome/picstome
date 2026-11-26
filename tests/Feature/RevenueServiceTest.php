<?php

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use App\Services\RevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('picstome.disk'));

    $this->revenue = app(RevenueService::class);
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['monthly_invoice_limit' => 10]);
    InvoiceSetting::factory()->for($this->team)->create();
});

/**
 * Issue an invoice and mark it as paid by hand.
 */
function paidInvoice(Team $team, float $total, mixed $paidAt = null): Invoice
{
    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($team, ['total' => $total]));

    $invoice->update(['paid_at' => $paidAt ?? now()]);

    return $invoice;
}

it('adds the completed payments of the period', function () {
    Payment::factory()->for($this->team)->create(['amount' => 10000, 'completed_at' => now()->subDays(2)]);
    Payment::factory()->for($this->team)->create(['amount' => 5050, 'completed_at' => now()]);

    expect($this->revenue->lastDays($this->team, 30))->toBe(150.5);
});

it('ignores the payments completed outside the period', function () {
    Payment::factory()->for($this->team)->create(['amount' => 10000, 'completed_at' => now()->subDays(31)]);
    Payment::factory()->for($this->team)->create(['amount' => 2000, 'completed_at' => null]);

    expect($this->revenue->lastDays($this->team, 30))->toBe(0.0);
});

it('adds the invoices marked as paid by hand', function () {
    paidInvoice($this->team, 605);

    expect($this->revenue->lastDays($this->team, 30))->toBe(605.0);
});

it('ignores the invoices marked as paid outside the period', function () {
    paidInvoice($this->team, 605, now()->subDays(31));

    expect($this->revenue->lastDays($this->team, 30))->toBe(0.0);
});

it('does not count twice an invoice settled by a payment', function () {
    $invoice = paidInvoice($this->team, 121);

    Payment::factory()->for($this->team)->create([
        'invoice_id' => $invoice->id,
        'amount' => 12100,
        'completed_at' => now(),
    ]);

    expect($this->revenue->lastDays($this->team, 30))->toBe(121.0);
});

it('ignores a rectified invoice, even when it was marked as paid', function () {
    $invoice = paidInvoice($this->team, 500);

    $invoice->forceFill(['status' => Invoice::STATUS_RECTIFIED])->saveQuietly();

    expect($this->revenue->lastDays($this->team, 30))->toBe(0.0);
});

it('counts the corrective invoice that was paid instead', function () {
    $original = paidInvoice($this->team, 500);

    $corrective = draftInvoice($this->team, [
        'total' => 300,
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
    ]);

    $corrective->forceFill([
        'status' => Invoice::STATUS_ISSUED,
        'paid_at' => now(),
    ])->saveQuietly();

    $original->forceFill(['status' => Invoice::STATUS_RECTIFIED])->saveQuietly();

    expect($this->revenue->lastDays($this->team, 30))->toBe(300.0);
});

it('converts an invoice issued in another currency to the currency of the team', function () {
    $invoice = paidInvoice($this->team, 100);

    $invoice->forceFill([
        'currency' => 'USD',
        'exchange_rate' => 0.9,
    ])->saveQuietly();

    expect($this->revenue->lastDays($this->team, 30))->toBe(90.0);
});

it('ignores an invoice in another currency without an exchange rate', function () {
    $invoice = paidInvoice($this->team, 100);

    $invoice->forceFill(['currency' => 'USD', 'exchange_rate' => null])->saveQuietly();

    expect($this->revenue->lastDays($this->team, 30))->toBe(0.0);
});

it('does not count a paid invoice rectified afterwards until its corrective is marked as paid', function () {
    $original = paidInvoice($this->team, 205.70);

    $corrective = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team, [
        'total' => 181.50,
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
    ]));

    expect($this->revenue->lastDays($this->team, 30))->toBe(0.0);

    $corrective->update(['paid_at' => now()]);

    expect($this->revenue->lastDays($this->team, 30))->toBe(181.5);
});

it('counts only the revenue between two moments', function () {
    $from = now()->subDays(50);
    $to = now()->subDays(10);

    Payment::factory()->for($this->team)->create(['amount' => 10000, 'completed_at' => $from->copy()->addDay()]);
    Payment::factory()->for($this->team)->create(['amount' => 20000, 'completed_at' => $from->copy()->subDay()]);
    Payment::factory()->for($this->team)->create(['amount' => 30000, 'completed_at' => $to->copy()->addDay()]);

    expect($this->revenue->between($this->team, $from, $to))->toBe(100.0);
});
