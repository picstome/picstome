<?php

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Issuing an invoice writes its PDF to the configured disk.
    Storage::fake(config('picstome.disk'));
});

it('allows users to dismiss a dashboard setup step', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    expect($team->dismissed_setup_steps)->toBeNull();

    $response = Livewire::actingAs($user)->test('pages::dashboard')
        ->call('dismissStep', 'portfolio');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->dismissed_setup_steps)->toContain('portfolio');
});

it('shows setup progress based on completed steps', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update([
        'brand_logo_path' => 'branding/logo.png',
        'dismissed_setup_steps' => ['portfolio'],
    ]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('Complete Your Account Setup')
        ->assertSee('1/5');
});

it('counts a text watermark as a configured setup step', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => 'Acme Studio',
    ]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('1/5');
});

it('counts the collected payments in the revenue of the last 30 days', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Payment::factory()->for($team)->create(['amount' => 15000, 'completed_at' => now()->subDays(3)]);
    Payment::factory()->for($team)->create(['amount' => 25000, 'completed_at' => now()]);
    Payment::factory()->for($team)->create(['amount' => 90000, 'completed_at' => now()->subDays(40)]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('€400.00');
});

it('counts an invoice marked as paid by hand in the revenue', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    InvoiceSetting::factory()->for($team)->create();

    $invoice = draftInvoice($team, ['total' => 605]);
    $invoice->status = Invoice::STATUS_ISSUED;
    $invoice->paid_at = now()->subDay();
    $invoice->save();

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('€605.00');
});

it('does not count twice an invoice settled by a payment', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    InvoiceSetting::factory()->for($team)->create();

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($team, ['total' => 121]));
    $invoice->update(['paid_at' => now()]);

    Payment::factory()->for($team)->create([
        'invoice_id' => $invoice->id,
        'amount' => 12100,
        'completed_at' => now(),
    ]);

    // The payment is the money that entered the account; the invoice it
    // settles is not added on top.
    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('€121.00')
        ->assertDontSee('€242.00');
});

it('skips a rectified invoice in the revenue', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    InvoiceSetting::factory()->for($team)->create();

    $original = draftInvoice($team, ['total' => 500]);
    $original->status = Invoice::STATUS_RECTIFIED;
    $original->paid_at = now()->subDay();
    $original->save();

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('€0.00');
});

it('keeps out of the revenue the invoices paid before the period', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    InvoiceSetting::factory()->for($team)->create();

    $invoice = draftInvoice($team, ['total' => 400]);
    $invoice->status = Invoice::STATUS_ISSUED;
    $invoice->paid_at = now()->subDays(45);
    $invoice->save();

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('€0.00');
});
