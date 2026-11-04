<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('connected apps page is displayed', function () {
    actingAs(User::factory()->withPersonalTeam()->create());

    get('/settings/apps')
        ->assertOk()
        ->assertSee('Connected apps');
});

test('lists only the current user sync tokens', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherUser = User::factory()->withPersonalTeam()->create();

    $user->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));
    $otherToken = $otherUser->createToken('picstome-sync-def456', ['sync'], now()->addDays(365));
    $unrelated = $user->createToken('other-app-ghi789', ['read'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.apps')
        ->assertSee('picstome-sync-abc123')
        ->assertSee('picstome-sync')
        ->assertSee('Active')
        ->assertSee('Scope')
        ->assertSee('sync')
        ->assertDontSee($otherToken->accessToken->name)
        ->assertDontSee($unrelated->accessToken->name);
});

test('marks expired tokens', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $user->createToken('picstome-sync-expired', ['sync'], now()->subDay());

    Livewire::actingAs($user)
        ->test('pages::settings.apps')
        ->assertSee('picstome-sync-expired')
        ->assertSee('Expired');
});

test('revokes the current user token', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $token = $user->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.apps')
        ->call('revoke', $token->accessToken->id)
        ->assertSee('No connected apps yet');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

test('cannot revoke another user token', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherToken = $otherUser->createToken('picstome-sync-other', ['sync'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.apps')
        ->call('revoke', $otherToken->accessToken->id);

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherToken->accessToken->id,
    ]);
});
