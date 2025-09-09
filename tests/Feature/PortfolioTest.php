<?php

use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('requires authentication for manage portfolio page', function () {
    get(route('portfolio'))->assertRedirect('/login');
});

it('allows users to add a gallery to portfolio', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $gallery = Gallery::factory()->for($team)->create(['is_public' => false]);

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('addToPortfolio', $gallery);

    $response->assertHasNoErrors();

    $gallery->refresh();
    expect($gallery->is_public)->toBeTrue();
});

it('allows users to remove a gallery from portfolio', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $gallery = Gallery::factory()->for($team)->create(['is_public' => true]);

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('removeFromPortfolio', $gallery);

    $response->assertHasNoErrors();

    $gallery->refresh();
    expect($gallery->is_public)->toBeFalse();
});

it('prevents users from managing portfolio galleries for other teams', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherTeam = Team::factory()->create();

    $otherGallery = Gallery::factory()->for($otherTeam)->create(['is_public' => true]);

    actingAs($user);

    get(route('portfolio'))->assertOk();

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('removeFromPortfolio', $otherGallery);

    $response->assertForbidden();
});

it('allows users to reorder portfolio galleries', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $gallery1 = Gallery::factory()->for($team)->create(['is_public' => true, 'portfolio_order' => 1]);
    $gallery2 = Gallery::factory()->for($team)->create(['is_public' => true, 'portfolio_order' => 2]);

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('reorderGallery', $gallery2, 1);

    $response->assertHasNoErrors();

    $gallery1->refresh();
    $gallery2->refresh();

    expect($gallery1->portfolio_order)->toEqual(2);
    expect($gallery2->portfolio_order)->toEqual(1);
});

it('prevents users from managing portfolio for other teams', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherTeam = Team::factory()->create();

    $otherGallery = Gallery::factory()->for($otherTeam)->create(['is_public' => true]);

    actingAs($user);

    get(route('portfolio'))
        ->assertOk()
        ->assertDontSee($otherGallery->name);

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('removeFromPortfolio', $otherGallery);

    $response->assertForbidden();
});
