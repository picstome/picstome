<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Payment;
use App\Models\Photoshoot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows only payments belonging to the users team', function () {
    $paymentA = Payment::factory()->for($this->team)->create();
    $paymentB = Payment::factory()->for(Team::factory())->create();
    $paymentC = Payment::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/payments');
    $component = Volt::test('pages.payments');

    $response->assertStatus(200);
    expect($component->payments->count())->toBe(2);
    expect($component->payments->contains($paymentA))->toBeTrue();
    expect($component->payments->contains($paymentB))->toBeFalse();
    expect($component->payments->contains($paymentC))->toBeTrue();
});

it('can edit a payment to assign a photoshoot', function () {
    $payment = Payment::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $component = Volt::actingAs($this->user)->test('pages.payments')
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

    $component = Volt::actingAs($this->user)->test('pages.payments')
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

    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->call('editPayment', $payment->id)
        ->set('paymentForm.photoshoot_id', $otherPhotoshoot->id)
        ->call('savePayment')
        ->assertHasErrors(['paymentForm.photoshoot_id' => 'exists']);
});

it('generates payment link with valid data', function () {
    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.currency', 'usd')
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink');

    $link = $component->paymentLink;
    expect($link)->not->toBeNull();
    expect($link)->toContain($this->team->handle);
    expect($link)->toContain("/@{$this->team->handle}/pay");
    expect($link)->toContain("/1000/");
    expect($link)->toContain("/Test%20payment");
});

it('fails validation if amount is missing', function () {
    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->set('linkForm.currency', 'usd')
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.amount' => 'required']);
});

it('fails validation if amount is not integer or < 1', function () {
    $component = Volt::test('pages.payments')
        ->set('linkForm.amount', 0)
        ->set('linkForm.currency', 'usd')
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.amount' => 'min']);

    $component->set('linkForm.amount', 'abc')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.amount' => 'integer']);
});

it('fails validation if currency is missing', function () {
    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.currency', '')
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.currency' => 'required']);
});

it('fails validation if currency is too long', function () {
    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.currency', str_repeat('a', 11))
        ->set('linkForm.description', 'Test payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.currency' => 'max']);
});

it('fails validation if description is missing', function () {
    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.currency', 'usd')
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.description' => 'required']);
});

it('fails validation if description is too long', function () {
    $component = Volt::actingAs($this->user)->test('pages.payments')
        ->set('linkForm.amount', 1000)
        ->set('linkForm.currency', 'usd')
        ->set('linkForm.description', str_repeat('a', 256))
        ->call('generatePaymentLink')
        ->assertHasErrors(['linkForm.description' => 'max']);
});
