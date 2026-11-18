<?php

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('picstome.disk'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['monthly_invoice_limit' => 10]);
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

/**
 * Issue a corrective invoice of the given one, with the same amounts.
 */
function correctiveInvoiceFor(InvoiceIssuanceService $issuance, Invoice $original): Invoice
{
    $draft = $original->team->invoices()->create([
        'customer_id' => $original->customer_id,
        'invoice_type' => $original->invoice_type,
        'issue_date' => now()->toDateString(),
        'currency' => $original->currency,
        'vat_percent' => $original->vat_percent,
        'subtotal' => $original->subtotal,
        'base_amount' => $original->base_amount,
        'vat_amount' => $original->vat_amount,
        'total' => $original->total,
        'amount_due' => $original->amount_due,
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
        'invoice_data' => $original->invoice_data,
    ]);

    return $issuance->issue($draft);
}

it('requires an explicit dry run or force option', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $this->artisan('invoices:delete', ['reference' => $invoice->full_number])
        ->assertFailed();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue();
});

it('deletes the invoice, its corrective invoice, the audit trail and the PDFs in a dry run', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));
    $corrective = correctiveInvoiceFor($this->issuance, $invoice);

    $this->artisan('invoices:delete', [
        'reference' => $corrective->full_number,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue()
        ->and(Invoice::whereKey($corrective->id)->exists())->toBeTrue()
        ->and(Storage::disk(config('picstome.disk'))->exists($invoice->pdf_path))->toBeTrue();
});

it('deletes the invoice family and rewinds the numbering', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));
    $corrective = correctiveInvoiceFor($this->issuance, $invoice);

    $this->artisan('invoices:delete', [
        'reference' => $invoice->full_number,
        '--force' => true,
    ])->assertSuccessful();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeFalse()
        ->and(Invoice::whereKey($corrective->id)->exists())->toBeFalse()
        ->and(Storage::disk(config('picstome.disk'))->exists($invoice->pdf_path))->toBeFalse()
        ->and(Storage::disk(config('picstome.disk'))->exists($corrective->pdf_path))->toBeFalse()
        ->and(Invoice::query()->where('team_id', $this->team->id)->count())->toBe(0);

    $state = $this->settings->fresh()->invoice_sequence_state ?? [];

    expect($state['F'][now()->year] ?? null)->toBeNull()
        ->and($state['R'][now()->year] ?? null)->toBeNull();

    $next = $this->issuance->issue(draftInvoice($this->team));

    expect($next->number)->toBe(1)
        ->and($next->full_number)->toBe('F-'.now()->year.'-0001')
        ->and($next->previous_fingerprint)->toBeNull();
});

it('resolves the invoice by its ULID', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $this->artisan('invoices:delete', [
        'reference' => $invoice->ulid,
        '--force' => true,
    ])->assertSuccessful();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeFalse();
});

it('refuses to delete an invoice that is not the last of its series', function () {
    $first = $this->issuance->issue(draftInvoice($this->team));
    $second = $this->issuance->issue(draftInvoice($this->team));

    $this->artisan('invoices:delete', [
        'reference' => $first->full_number,
        '--force' => true,
    ])->assertFailed();

    expect(Invoice::whereKey($first->id)->exists())->toBeTrue()
        ->and(Invoice::whereKey($second->id)->exists())->toBeTrue();
});

it('refuses to delete an invoice with payments without force and unlinks them with it', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $payment = Payment::factory()->for($this->team)->create([
        'invoice_id' => $invoice->id,
        'completed_at' => now(),
    ]);

    $this->artisan('invoices:delete', [
        'reference' => $invoice->full_number,
        '--dry-run' => true,
    ])->assertFailed();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeTrue();

    $this->artisan('invoices:delete', [
        'reference' => $invoice->full_number,
        '--force' => true,
    ])->assertSuccessful();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeFalse()
        ->and($payment->fresh()->invoice_id)->toBeNull();
});

it('fails when the invoice does not exist', function () {
    $this->artisan('invoices:delete', [
        'reference' => 'F-1999-0001',
        '--dry-run' => true,
    ])->assertFailed();
});

it('lists the teams when the reference is ambiguous', function () {
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherTeam = $otherUser->currentTeam;
    $otherTeam->update(['monthly_invoice_limit' => 10]);
    InvoiceSetting::factory()->for($otherTeam)->create();

    $mine = $this->issuance->issue(draftInvoice($this->team));
    $theirs = $this->issuance->issue(draftInvoice($otherTeam, ['team_id' => $otherTeam->id]));

    expect($theirs->full_number)->toBe($mine->full_number);

    // The table of candidates is written row by row, so the output is read
    // from the command instead of matching it line by line.
    $exitCode = Artisan::call('invoices:delete', [
        'reference' => $mine->full_number,
        '--dry-run' => true,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('matches more than one team')
        ->and($output)->toContain($this->team->name)
        ->and($output)->toContain($this->user->email)
        ->and($output)->toContain($otherUser->email);

    expect(Invoice::whereKey($mine->id)->exists())->toBeTrue()
        ->and(Invoice::whereKey($theirs->id)->exists())->toBeTrue();
});

it('deletes the invoice of the requested team when the number is repeated', function () {
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherTeam = $otherUser->currentTeam;
    $otherTeam->update(['monthly_invoice_limit' => 10]);
    InvoiceSetting::factory()->for($otherTeam)->create();

    $mine = $this->issuance->issue(draftInvoice($this->team));
    $theirs = $this->issuance->issue(draftInvoice($otherTeam, ['team_id' => $otherTeam->id]));

    $this->artisan('invoices:delete', [
        'reference' => $mine->full_number,
        '--team' => $this->team->id,
        '--force' => true,
    ])->assertSuccessful();

    expect(Invoice::whereKey($mine->id)->exists())->toBeFalse()
        ->and(Invoice::whereKey($theirs->id)->exists())->toBeTrue();
});
