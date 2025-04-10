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

test('users can view their team photoshoots', function () {
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for(Team::factory())->create();
    $photoshootC = Photoshoot::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/photoshoots');
    $component = Volt::test('pages.photoshoots');

    $response->assertStatus(200);
    $component->assertViewHas('photoshoots');
    expect($component->viewData('photoshoots')->contains($photoshootA))->toBeTrue();
    expect($component->viewData('photoshoots')->contains($photoshootB))->toBeFalse();
    expect($component->viewData('photoshoots')->contains($photoshootC))->toBeTrue();
});

test('can create a photoshoot', function () {
    $component = Volt::actingAs($this->user)
        ->test('pages.photoshoots')
        ->set('form.name', 'John\'s Photoshoot')
        ->set('form.customerName', 'John Doe')
        ->call('save');

    expect($this->team->photoshoots()->count())->toBe(1);
});

test('can create a photoshoot with customer email', function () {
    $component = Volt::actingAs($this->user)
        ->test('pages.photoshoots')
        ->set('form.name', 'John\'s Photoshoot')
        ->set('form.customerName', 'John Doe')
        ->set('form.customerEmail', 'john@example.com')
        ->call('save');

    expect($this->team->photoshoots()->first()->customer_email)->toBe('john@example.com');
});

test('guests cannot create photoshoots', function () {
    $component = Volt::test('pages.photoshoots')->call('save');

    $component->assertStatus(403);
});
