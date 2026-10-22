<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use App\Services\InvoiceFromPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows only payments belonging to users team', function () {
    $paymentA = Payment::factory()->for($this->team)->create();
    $paymentB = Payment::factory()->for(Team::factory())->create();
    $paymentC = Payment::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/payments');
    $component = Livewire::test('pages::payments');

    $response->assertStatus(200);
    expect($component->payments->count())->toBe(2);
    expect($component->payments->contains($paymentA))->toBeTrue();
    expect($component->payments->contains($paymentB))->toBeFalse();
    expect($component->payments->contains($paymentC))->toBeTrue();
});

it('can edit a payment to assign a photoshoot', function () {
    $payment = Payment::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->call('editPayment', $payment->id)
        ->set('paymentForm.photoshoot_id', $photoshoot->id)
        ->call('savePayment');

    $payment->refresh();

    expect($payment->photoshoot_id)->toBe($photoshoot->id);
});

it('can change or remove the assigned photoshoot', function () {
    $payment = Payment::factory()->for($this->team)->create();
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->call('editPayment', $payment->id)
        ->set('paymentForm.photoshoot_id', $photoshootA->id)
        ->call('savePayment');

    $payment->refresh();

    expect($payment->photoshoot_id)->toBe($photoshootA->id);

    $component->call('editPayment', $payment->id)
        ->set('paymentForm.photoshoot_id', $photoshootB->id)
        ->call('savePayment');

    $payment->refresh();

    expect($payment->photoshoot_id)->toBe($photoshootB->id);

    $component->call('editPayment', $payment->id)
        ->set('paymentForm.photoshoot_id', null)
        ->call('savePayment');

    $payment->refresh();

    expect($payment->photoshoot_id)->toBeNull();
});

it('cannot assign a photoshoot from another team', function () {
    $payment = Payment::factory()->for($this->team)->create();
    $otherTeam = Team::factory()->create();
    $otherPhotoshoot = Photoshoot::factory()->for($otherTeam)->create();

    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->call('editPayment', $payment->id)
        ->set('paymentForm.photoshoot_id', $otherPhotoshoot->id)
        ->call('savePayment')
        ->assertHasErrors(['paymentForm.photoshoot_id' => 'exists']);
});

it('generates a payment link with valid data', function () {
    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.description', 'Test payment')
        ->set('linkForm.booking', false)
        ->call('generatePaymentLink');

    $link = $component->paymentLink;
    expect($link)->not->toBeNull();
    expect($link)->toContain($this->team->handle);
    expect($link)->toContain("/@{$this->team->handle}/pay");
    expect($link)->toContain('/1000/');
    expect($link)->toContain('/Test%20payment');
});

it('fails validation if amount is missing', function () {
    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.amount' => 'required']);
});

it('fails validation if amount is not integer or < 1', function () {
    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->set('linkForm.amount', 0)
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.amount' => 'min']);

    $component->set('linkForm.amount', 'abc')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.amount' => 'integer']);
});

it('fails validation if description is missing', function () {
    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->set('linkForm.amount', 1000)
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.description' => 'required']);
});

it('fails validation if description is too long', function () {
    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.description', str_repeat('a', 256))
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.description' => 'max']);
});

it('can delete a payment', function () {
    $payment = Payment::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::payments')
        ->call('deletePayment', $payment->id);

    expect($payment->fresh())->toBeNull();
});

it('creates the draft invoice of a payment', function () {
    $payment = Payment::factory()->for($this->team)->create(['amount' => 15000, 'currency' => 'eur']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->call('createInvoice', $payment->id)
        ->assertHasNoErrors();

    $invoice = Invoice::where('team_id', $this->team->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and((float) $invoice->total)->toBe(150.0)
        ->and($payment->fresh()->invoice_id)->toBe($invoice->id);

    $component->assertRedirect(route('invoices.create', ['invoice' => $invoice->ulid]));
});

it('does not invoice the same payment twice', function () {
    $payment = Payment::factory()->for($this->team)->create();

    app(InvoiceFromPaymentService::class)->createFromPayment($payment, $this->user);

    Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->call('createInvoice', $payment->id)
        ->assertHasErrors(['payment' => 'This payment is already invoiced.']);

    expect(Invoice::where('team_id', $this->team->id)->count())->toBe(1);
});

it('cannot invoice a payment of another team', function () {
    $otherTeam = Team::factory()->create();

    $payment = Payment::factory()->for($otherTeam)->create();

    Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->call('createInvoice', $payment->id)
        ->assertStatus(404);

    expect(Invoice::count())->toBe(0);
});
