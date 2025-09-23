<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Payment;
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
