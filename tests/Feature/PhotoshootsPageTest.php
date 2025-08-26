<?php

use App\Models\Photoshoot;
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

it('shows only photoshoots belonging to the users team', function () {
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for(Team::factory())->create();
    $photoshootC = Photoshoot::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/photoshoots');
    $component = Volt::test('pages.photoshoots');

    $response->assertStatus(200);
    expect($component->photoshoots->count())->toBe(2);
    expect($component->photoshoots->contains($photoshootA))->toBeTrue();
    expect($component->photoshoots->contains($photoshootB))->toBeFalse();
    expect($component->photoshoots->contains($photoshootC))->toBeTrue();
});

it('allows a user to create a photoshoot', function () {
    $component = Volt::actingAs($this->user)
        ->test('pages.photoshoots')
        ->set('form.name', 'John\'s Photoshoot')
        ->set('form.customerName', 'John Doe')
        ->call('save');

    expect($this->team->photoshoots()->count())->toBe(1);
});

it('allows a user to create a photoshoot with a customer email', function () {
    $component = Volt::actingAs($this->user)
        ->test('pages.photoshoots')
        ->set('form.name', 'John\'s Photoshoot')
        ->set('form.customerName', 'John Doe')
        ->set('form.customerEmail', 'john@example.com')
        ->call('save');

    expect($this->team->photoshoots()->first()->customer_email)->toBe('john@example.com');
});

it('forbids guests from creating photoshoots', function () {
    $component = Volt::test('pages.photoshoots')->call('save');

    $component->assertStatus(403);
});
