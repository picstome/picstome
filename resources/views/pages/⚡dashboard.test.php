<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows users to dismiss a dashboard setup step', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    expect($team->dismissed_setup_steps)->toBeNull();

    $response = Livewire::actingAs($user)->test('pages::dashboard')
        ->call('dismissStep', 'portfolio');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->dismissed_setup_steps)->toContain('portfolio');
});

it('shows setup progress based on completed steps', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update([
        'brand_logo_path' => 'branding/logo.png',
        'dismissed_setup_steps' => ['portfolio'],
    ]);

    Livewire::actingAs($user)->test('pages::dashboard')
        ->assertSee('Complete Your Account Setup')
        ->assertSee('1/5');
});
