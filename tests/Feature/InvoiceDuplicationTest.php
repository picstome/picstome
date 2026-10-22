<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceDuplicationService;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 10]);

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->duplication = app(InvoiceDuplicationService::class);
});

it('duplicates an issued invoice as a draft', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));

    $draft = $this->duplication->duplicate($original, $this->user);

    expect($draft->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($draft->customer_id)->toBe($original->customer_id)
        ->and($draft->invoice_type)->toBe($original->invoice_type)
        ->and((float) $draft->subtotal)->toBe((float) $original->subtotal)
        ->and((float) $draft->vat_amount)->toBe((float) $original->vat_amount)
        ->and((float) $draft->total)->toBe((float) $original->total)
        ->and($draft->number)->toBeNull()
        ->and($draft->series)->toBeNull()
        ->and($draft->year)->toBeNull()
        ->and($draft->fingerprint)->toBeNull()
        ->and($draft->previous_fingerprint)->toBeNull()
        ->and($draft->pdf_path)->toBeNull()
        ->and($draft->public_token)->toBeNull()
        ->and($draft->issued_at)->toBeNull()
        ->and($draft->rectifies_invoice_id)->toBeNull()
        ->and($draft->issue_date->toDateString())->toBe(now()->toDateString())
        ->and($draft->due_date)->toBeNull();
});

it('copies the line items, the discount and the notes', function () {
    $invoice = draftInvoice($this->team);

    $invoice->update([
        'discount_type' => 'percentage',
        'discount_amount' => 10,
        'irpf_percent' => 15,
        'invoice_data' => array_merge($invoice->invoice_data, [
            'line_items' => [
                ['description' => 'Photo session', 'quantity' => 2, 'unit_price' => 100],
                ['description' => 'Album', 'quantity' => 1, 'unit_price' => 50],
            ],
            'payment_terms' => '30 days',
            'notes' => 'Thank you',
            'apply_irpf' => true,
        ]),
    ]);

    $original = $this->issuance->issue($invoice->fresh());

    $draft = $this->duplication->duplicate($original, $this->user);

    expect($draft->invoice_data['line_items'])->toHaveCount(2)
        ->and($draft->invoice_data['line_items'][0]['description'])->toBe('Photo session')
        ->and($draft->invoice_data['line_items'][0]['id'])->not->toBeEmpty()
        ->and($draft->invoice_data['payment_terms'])->toBe('30 days')
        ->and($draft->invoice_data['notes'])->toBe('Thank you')
        ->and($draft->invoice_data['apply_irpf'])->toBeTrue()
        ->and($draft->discount_type)->toBe('percentage')
        ->and((float) $draft->discount_amount)->toBe(10.0)
        ->and((float) $draft->discount_total)->toBe(25.0)
        ->and((float) $draft->irpf_amount)->toBeGreaterThan(0);
});

it('only duplicates issued invoices', function () {
    $draft = draftInvoice($this->team);

    expect(fn () => $this->duplication->duplicate($draft))
        ->toThrow(ValidationException::class, 'Only issued invoices can be duplicated');
});

it('duplicates a corrective invoice as a normal invoice', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));
    $rectificative = $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong client name',
    ]));

    $draft = $this->duplication->duplicate($rectificative->fresh(), $this->user);

    expect($draft->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($draft->rectifies_invoice_id)->toBeNull()
        ->and($draft->rectification_type)->toBeNull()
        ->and($draft->rectification_reason)->toBeNull()
        ->and($draft->invoice_type)->toBe($rectificative->invoice_type);
});

it('records where the draft comes from in the audit trail', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));

    $draft = $this->duplication->duplicate($original, $this->user);

    $event = $draft->events()->where('event', InvoiceEvent::CREATED)->first();

    expect($event)->not->toBeNull()
        ->and($event->user_id)->toBe($this->user->id)
        ->and($event->context['duplicated_from']['id'])->toBe($original->id)
        ->and($event->context['duplicated_from']['number'])->toBe($original->full_number)
        ->and($event->context['duplicated_from']['issue_date'])->toBe($original->issue_date->format('Y-m-d'));
});

it('does not consume the monthly limit', function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 1]);

    $original = $this->issuance->issue(draftInvoice($this->team));

    $draft = $this->duplication->duplicate($original, $this->user);

    expect($draft->status)->toBe(Invoice::STATUS_DRAFT);
});

it('recalculates the totals from the copied line items', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));

    $draft = $this->duplication->duplicate($original);

    expect((float) $draft->base_amount)->toBe(100.0)
        ->and((float) $draft->vat_amount)->toBe(21.0)
        ->and((float) $draft->total)->toBe(121.0)
        ->and((float) $draft->amount_due)->toBe(121.0);
});
