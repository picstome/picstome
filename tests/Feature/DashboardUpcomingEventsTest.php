<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('picstome.disk'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    InvoiceSetting::factory()->for($this->team)->create();
});

function issuedInvoice(Team $team, array $attributes = []): Invoice
{
    return draftInvoice($team, array_merge([
        'status' => Invoice::STATUS_ISSUED,
        'series' => 'F',
        'year' => (int) now()->year,
        'number' => 1,
        'issued_at' => now(),
    ], $attributes));
}

it('lists issued invoices with a pending amount as reminders', function () {
    $invoice = issuedInvoice($this->team, [
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertSee('Upcoming Events & Reminders')
        ->assertSee($invoice->full_number)
        ->assertSee('Payment pending');
});

it('flags an invoice past its due date as overdue', function () {
    $invoice = issuedInvoice($this->team, [
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertSee($invoice->full_number)
        ->assertSee('Overdue');
});

it('hides invoices settled by their linked payments', function () {
    $invoice = issuedInvoice($this->team, [
        'due_date' => now()->addDays(3)->toDateString(),
        'total' => 121,
    ]);

    Payment::factory()->for($this->team)->create([
        'invoice_id' => $invoice->id,
        'amount' => 12100,
        'currency' => 'eur',
    ]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertDontSee($invoice->full_number);
});

it('hides invoices the team marked as paid', function () {
    $invoice = issuedInvoice($this->team, [
        'due_date' => now()->addDays(3)->toDateString(),
        'paid_at' => now(),
    ]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertDontSee($invoice->full_number);
});

it('ignores draft invoices and invoices of another team', function () {
    $draft = draftInvoice($this->team, ['number' => 7, 'series' => 'F']);

    $other = User::factory()->withPersonalTeam()->create();
    InvoiceSetting::factory()->for($other->currentTeam)->create();
    $foreign = issuedInvoice($other->currentTeam, ['number' => 99]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertDontSee($draft->full_number)
        ->assertDontSee($foreign->full_number);
});

it('orders overdue invoices before upcoming ones', function () {
    issuedInvoice($this->team, [
        'number' => 1,
        'due_date' => now()->addDays(10)->toDateString(),
    ]);
    issuedInvoice($this->team, [
        'number' => 2,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);

    $events = Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->instance()
        ->getUpcomingEventsAndReminders();

    expect($events->first()['type'])->toBe('invoice')
        ->and($events->first()['is_overdue'])->toBeTrue()
        ->and($events->first()['label'])->toBe('F-'.now()->year.'-0002');
});

it('shows the customer name next to an invoice reminder', function () {
    $customer = Customer::factory()->for($this->team)->create(['name' => 'Ana Fotografía']);

    $invoice = issuedInvoice($this->team, [
        'customer_id' => $customer->id,
        'due_date' => now()->addDays(4)->toDateString(),
    ]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertSee($invoice->full_number)
        ->assertSee('Ana Fotografía');
});

it('shows the pending amount inside the invoice status badge', function () {
    issuedInvoice($this->team, [
        'due_date' => now()->addDays(2)->toDateString(),
        'total' => 121.5,
        'amount_due' => 121.5,
        'currency' => 'EUR',
    ]);

    $invoiceEvent = Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->instance()
        ->getUpcomingEventsAndReminders()
        ->firstWhere('type', 'invoice');

    expect($invoiceEvent['badge'])->toBe('Payment pending · 121.50 EUR');
});

it('does not render a dedicated amount column', function () {
    issuedInvoice($this->team, [
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    Livewire::actingAs($this->user)->test('pages::dashboard')
        ->assertDontSee('Amount');
});

it('falls back to the issue date when the invoice has no due date', function () {
    $invoice = issuedInvoice($this->team, [
        'due_date' => null,
        'issue_date' => now()->subDays(10)->toDateString(),
    ]);

    $events = Livewire::actingAs($this->user)
        ->test('pages::dashboard')
        ->instance()
        ->getUpcomingEventsAndReminders();

    $reminder = $events->firstWhere('label', $invoice->full_number);

    expect($reminder)->not->toBeNull()
        ->and($reminder['is_overdue'])->toBeFalse()
        ->and($reminder['date']->toDateString())->toBe(now()->subDays(10)->toDateString());
});

it('renders the reminders table without overflowing on mobile', function () {
    issuedInvoice($this->team, [
        'due_date' => now()->subDays(3)->toDateString(),
    ]);

    $this->actingAs($this->user);

    $page = visit('/dashboard');

    $page->assertSee('Upcoming Events & Reminders')
        ->assertSee('Overdue')
        ->assertNoJavascriptErrors();

    foreach ([320, 375] as $width) {
        $page->resize($width, 900);
        $page->wait(0.1);

        expect($page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))
            ->toBeTrue();
    }
})->group('browser');
