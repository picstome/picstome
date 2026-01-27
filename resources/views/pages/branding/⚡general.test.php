<?php

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

it('redirects main branding page to general settings', function () {
    $response = actingAs($this->user)->get('/branding');

    $response->assertRedirect('/branding/general');
});

it('allows users to see the general branding page', function () {
    $response = actingAs($this->user)->get('/branding/general');

    $response->assertStatus(200);
});

it('prevents guests from viewing branding pages', function () {
    $response = get('/branding/general');

    $response->assertRedirect('/login');
});

it('can reset dismissed setup steps from general branding page', function () {
    $this->team->dismissed_setup_steps = ['portfolio', 'payments'];
    $this->team->save();

    expect($this->team->dismissed_setup_steps)->toContain('portfolio');
    expect($this->team->dismissed_setup_steps)->toContain('payments');

    Livewire::actingAs($this->user)->test('pages::branding.general')
        ->call('resetDismissedSetupSteps');

    $this->team->refresh();
    expect($this->team->dismissed_setup_steps)->toBe([]);
});
