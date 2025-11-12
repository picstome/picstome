<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('allows users to dismiss a dashboard setup step', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    expect($team->dismissed_setup_steps)->toBeNull();

    $response = Volt::actingAs($user)->test('pages.dashboard')
        ->call('dismissStep', 'portfolio');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->dismissed_setup_steps)->toContain('portfolio');
});
