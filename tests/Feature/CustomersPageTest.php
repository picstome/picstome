<?php

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows only customers belonging to the users team', function () {
    $customerA = Customer::factory()->for($this->team)->create();
    $customerB = Customer::factory()->for(Team::factory())->create();
    $customerC = Customer::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/customers');
    $component = Volt::test('pages.customers');

    $response->assertStatus(200);
    expect($component->customers->count())->toBe(2);
    expect($component->customers->contains($customerA))->toBeTrue();
    expect($component->customers->contains($customerB))->toBeFalse();
    expect($component->customers->contains($customerC))->toBeTrue();
});

it('allows a user to create a customer', function () {
    Volt::actingAs($this->user)
        ->test('pages.customers')
        ->set('form.name', 'Jane Doe')
        ->set('form.email', 'jane@example.com')
        ->call('save');

    expect($this->team->customers()->count())->toBe(1);
});

it('allows a user to create a customer with optional fields', function () {
    Volt::actingAs($this->user)
        ->test('pages.customers')
        ->set('form.name', 'Jane Doe')
        ->set('form.email', 'jane@example.com')
        ->set('form.phone', '1234567890')
        ->set('form.birthdate', '1990-01-01')
        ->set('form.notes', 'VIP customer')
        ->call('save');

    $customer = $this->team->customers()->first();
    expect($customer->phone)->toBe('1234567890');
    expect($customer->birthdate)->toBe('1990-01-01');
    expect($customer->notes)->toBe('VIP customer');
});

it('enforces unique email per team', function () {
    Customer::factory()->for($this->team)->create(['email' => 'unique@example.com']);

    Volt::actingAs($this->user)
        ->test('pages.customers')
        ->set('form.name', 'Another')
        ->set('form.email', 'unique@example.com')
        ->call('save')
        ->assertHasErrors(['form.email' => 'unique']);
});

it('forbids guests from creating customers', function () {
    Volt::test('pages.customers')->call('save')->assertStatus(403);
});
