<?php

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Payment;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows a customer and their related photoshoots, galleries, contracts, and payments', function () {
    $customer = Customer::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->for($customer)->create();
    $gallery = Gallery::factory()->for($this->team)->for($photoshoot)->create();
    $contract = Contract::factory()->for($this->team)->for($photoshoot)->create();
    $payment = Payment::factory()->for($this->team)->for($photoshoot)->create();

    $response = actingAs($this->user)->get("/customers/{$customer->id}");

    $response->assertStatus(200);
});

it('shows a customer with no related records', function () {
    $customer = Customer::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get("/customers/{$customer->id}");

    $response->assertStatus(200);
});

it('forbids guests from viewing a customer', function () {
    $customer = Customer::factory()->for($this->team)->create();
    $response = get("/customers/{$customer->id}");
    $response->assertRedirect('/login');
});

it('forbids users from viewing customers of other teams', function () {
    $otherTeam = Team::factory()->create();
    $customer = Customer::factory()->for($otherTeam)->create();
    $response = actingAs($this->user)->get("/customers/{$customer->id}");
    $response->assertStatus(403);
});

it('can edit customer notes via Livewire', function () {
    $customer = Customer::factory()->for($this->team)->create(['notes' => 'Old notes']);
    actingAs($this->user);

    Livewire::actingAs($this->user)->test('pages::customers.show', ['customer' => $customer])
        ->call('startEditingNotes')
        ->set('editedNotes', 'New notes with **markdown**')
        ->call('saveNotes')
        ->assertSet('editingNotes', false);

    $customer->refresh();
    expect($customer->notes)->toBe('New notes with **markdown**');
    expect($customer->formatted_notes)->toContain('<strong>markdown</strong>');
});
