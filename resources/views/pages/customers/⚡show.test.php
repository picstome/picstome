<?php

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Invoice;
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

it('shows the client link modal with the customer share url and shared galleries count', function () {
    $customer = Customer::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->for($customer)->create();
    Gallery::factory()->shared()->for($this->team)->for($photoshoot)->count(2)->create();

    $response = actingAs($this->user)->get("/customers/{$customer->id}");

    $response->assertStatus(200)
        ->assertSee(__('Client link'))
        ->assertSee(route('clients.show', $customer))
        ->assertSee(__('Shared galleries: :count', ['count' => 2]))
        ->assertSee(__('Remember that the link only shows shared galleries without a password.'));
});

it('hides the client link and shows a message when there are no shared galleries', function () {
    $customer = Customer::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->for($customer)->create();
    Gallery::factory()->unshared()->for($this->team)->for($photoshoot)->create();
    Gallery::factory()->shared()->protected()->for($this->team)->for($photoshoot)->create();

    $response = actingAs($this->user)->get("/customers/{$customer->id}");

    $response->assertStatus(200)
        ->assertSee(__('This client has no shared galleries.'))
        ->assertDontSee(__('Client link'))
        ->assertDontSee(route('clients.show', $customer));
});

it('counts only shared galleries without a password', function () {
    $customer = Customer::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->for($customer)->create();
    Gallery::factory()->shared()->for($this->team)->for($photoshoot)->create();
    Gallery::factory()->shared()->protected()->for($this->team)->for($photoshoot)->create();
    Gallery::factory()->unshared()->for($this->team)->for($photoshoot)->create();

    $response = actingAs($this->user)->get("/customers/{$customer->id}");

    $response->assertStatus(200)
        ->assertSee(__('Shared galleries: :count', ['count' => 1]))
        ->assertDontSee(__('Shared galleries: :count', ['count' => 2]));
});

it('shows the invoices of the client and the option to create one', function () {
    $customer = Customer::factory()->for($this->team)->create();

    $invoice = Invoice::factory()->for($this->team)->for($customer)->create([
        'status' => Invoice::STATUS_ISSUED,
        'series' => 'F',
        'year' => (int) now()->year,
        'number' => 1,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::customers.show', ['customer' => $customer])
        ->assertSee('Invoices')
        ->assertSee($invoice->full_number)
        ->assertSee(route('invoices.create', ['customer' => $customer->ulid]), false);
});
