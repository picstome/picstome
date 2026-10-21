<?php

use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 10]);

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

it('shows a public invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $this->get('/i/'.$invoice->public_token)
        ->assertOk()
        ->assertSee($invoice->full_number)
        ->assertSee('Verified')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('records the public view', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $this->get('/i/'.$invoice->public_token)->assertOk();

    expect($invoice->events()->pluck('event'))->toContain(InvoiceEvent::PUBLIC_VIEWED);
});

it('records the public view once a day', function () {
    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    $this->get('/i/'.$invoice->public_token)->assertOk();
    $this->get('/i/'.$invoice->public_token)->assertOk();

    expect($invoice->events()->where('event', InvoiceEvent::PUBLIC_VIEWED)->count())->toBe(1);
});

it('does not show an unknown token', function () {
    $this->get('/i/'.str_repeat('a', 40))->assertNotFound();
});

it('does not show a revoked link', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $invoice->update([
        'public_token' => null,
        'public_token_revoked_at' => now(),
    ]);

    $this->get('/i/'.$invoice->public_token)->assertNotFound();
});

it('does not show a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    $invoice->update(['public_token' => str_repeat('b', 40)]);

    $this->get('/i/'.$invoice->public_token)->assertNotFound();
});
