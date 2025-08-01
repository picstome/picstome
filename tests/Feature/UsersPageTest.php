<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('render the users page', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    $response = actingAs($user)->get('/users');

    $response->assertStatus(200);
});

it('dies if user is not admin', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    $response = actingAs($user)->get('/users');

    $response->assertStatus(403);
});

it('admin can update a user custom storage limit', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);
    $user = User::factory()->withPersonalTeam()->create();

    Volt::actingAs($admin)
        ->test('pages.users')
        ->call('editUser', $user->id)
        ->set('userForm.custom_storage_limit', 12)
        ->call('saveUser')
        ->assertHasNoErrors();

    $user->refresh();

    $team = $user->personalTeam();
    expect($team->custom_storage_limit)->toBe(12 * 1024 * 1024 * 1024);
});

it('saves unlimited when storage limit is null', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();
    $team->update(['custom_storage_limit' => 123456789]);
    expect($team->has_unlimited_storage)->toBeFalse();

    Volt::actingAs($admin)
        ->test('pages.users')
        ->call('editUser', $user->id)
        ->set('userForm.custom_storage_limit', null)
        ->call('saveUser')
        ->assertHasNoErrors();

    $team->refresh();
    expect($team->has_unlimited_storage)->toBeTrue();
});
