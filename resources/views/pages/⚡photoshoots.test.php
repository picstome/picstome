<?php

use App\Models\Customer;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows only photoshoots belonging to users team', function () {
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for(Team::factory())->create();
    $photoshootC = Photoshoot::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/photoshoots');
    $component = Livewire::test('pages::photoshoots');

    $response->assertStatus(200);
    expect($component->photoshoots->count())->toBe(2);
    expect($component->photoshoots->contains($photoshootA))->toBeTrue();
    expect($component->photoshoots->contains($photoshootB))->toBeFalse();
    expect($component->photoshoots->contains($photoshootC))->toBeTrue();
});

it('allows a user to create a photoshoot with a customer email', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots')
        ->set('form.name', 'John\'s Photoshoot')
        ->set('form.customerName', 'John Doe')
        ->set('form.customerEmail', 'john@example.com')
        ->call('save');

    $customer = $this->team->photoshoots()->first()->customer;
    expect($customer->name)->toBe('John Doe');
    expect($customer->email)->toBe('john@example.com');
});

it('allows a user to create a photoshoot with an existing customer', function () {
    $customer = Customer::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots')
        ->set('form.name', 'Jane Photoshoot')
        ->set('form.customer', $customer->id)
        ->call('save');

    $photoshoot = $this->team->photoshoots()->first();
    expect($photoshoot->customer->is($customer))->toBeTrue();
});
