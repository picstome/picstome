<?php

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Photoshoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->other = User::factory()->withPersonalTeam()->create();
});

it('lets the owner update and delete their gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    expect(Gate::forUser($this->user)->allows('update', $gallery))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('delete', $gallery))->toBeTrue();
});

it('blocks another team from updating or deleting a gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    expect(Gate::forUser($this->other)->allows('update', $gallery))->toBeFalse()
        ->and(Gate::forUser($this->other)->allows('delete', $gallery))->toBeFalse();
});

it('lets the owner update, execute and delete their contract', function () {
    $contract = Contract::factory()->for($this->team)->create();

    expect(Gate::forUser($this->user)->allows('update', $contract))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('execute', $contract))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('delete', $contract))->toBeTrue();
});

it('blocks another team from updating, executing or deleting a contract', function () {
    $contract = Contract::factory()->for($this->team)->create();

    expect(Gate::forUser($this->other)->allows('update', $contract))->toBeFalse()
        ->and(Gate::forUser($this->other)->allows('execute', $contract))->toBeFalse()
        ->and(Gate::forUser($this->other)->allows('delete', $contract))->toBeFalse();
});

it('lets the owner update and delete their photoshoot', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    expect(Gate::forUser($this->user)->allows('update', $photoshoot))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('delete', $photoshoot))->toBeTrue();
});

it('blocks another team from updating or deleting a photoshoot', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    expect(Gate::forUser($this->other)->allows('update', $photoshoot))->toBeFalse()
        ->and(Gate::forUser($this->other)->allows('delete', $photoshoot))->toBeFalse();
});

it('lets the owner update and delete their customer', function () {
    $customer = Customer::factory()->for($this->team)->create();

    expect(Gate::forUser($this->user)->allows('update', $customer))->toBeTrue()
        ->and(Gate::forUser($this->user)->allows('delete', $customer))->toBeTrue();
});

it('blocks another team from updating or deleting a customer', function () {
    $customer = Customer::factory()->for($this->team)->create();

    expect(Gate::forUser($this->other)->allows('update', $customer))->toBeFalse()
        ->and(Gate::forUser($this->other)->allows('delete', $customer))->toBeFalse();
});

it('denies every photo ability when the gallery row is missing', function () {
    $photo = Photo::factory()->create(['gallery_id' => 999999]);

    expect(Gate::forUser($this->user)->allows('view', $photo))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('update', $photo))->toBeFalse()
        ->and(Gate::forUser($this->user)->allows('delete', $photo))->toBeFalse();
});

it('refuses to mount the gallery page for another team', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->other)
        ->get("/galleries/{$gallery->id}")
        ->assertStatus(403);
});

it('refuses to mount the contract page for another team', function () {
    $contract = Contract::factory()->for($this->team)->create();

    $this->actingAs($this->other)
        ->get("/contracts/{$contract->id}")
        ->assertStatus(403);
});

it('refuses to mount the photoshoot page for another team', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $this->actingAs($this->other)
        ->get("/photoshoots/{$photoshoot->id}")
        ->assertStatus(403);
});

it('refuses to mount the customer page for another team', function () {
    $customer = Customer::factory()->for($this->team)->create();

    $this->actingAs($this->other)
        ->get("/customers/{$customer->id}")
        ->assertStatus(403);
});
