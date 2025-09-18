<?php

use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('allows users to disable the public portfolio page for their team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('disablePortfolioPage');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->portfolio_public_disabled)->toBeTrue();
});

it('returns 404 for public portfolio page when disabled', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update(['portfolio_public_disabled' => true]);

    get(route('portfolio.index', ['handle' => $team->handle]))
        ->assertNotFound();
});

it('allows users to re-enable the public portfolio page for their team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update(['portfolio_public_disabled' => true]);
    $team->portfolio_public_disabled = true;
    $team->save();

    $response = Volt::actingAs($user)->test('pages.portfolio')
        ->call('enablePortfolioPage');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->portfolio_public_disabled)->toBeFalse();
});

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
    expect($gallery->expiration_date)->toBeNull();
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
    expect($gallery->expiration_date)->not->toBeNull();
    expect($gallery->expiration_date->format('Y-m-d'))->toBe(now()->addMonth()->format('Y-m-d'));
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
