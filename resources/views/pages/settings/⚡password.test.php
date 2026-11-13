<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.password')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.password')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});

test('updating the password logs out other sessions', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $originalToken = $user->remember_token;

    $this->actingAs($user);

    DB::table('sessions')->insert([
        'id' => 'other-browser-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Firefox)',
        'payload' => base64_encode('session-payload'),
        'last_activity' => time(),
    ]);

    $response = Livewire::test('pages::settings.password')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect($user->refresh()->remember_token)->not->toBe($originalToken);
});

test('the old connected apps page redirects to the password page', function () {
    actingAs(User::factory()->withPersonalTeam()->create());

    get('/settings/apps')->assertRedirect('/settings/password');
});

test('settings sidebar no longer links to a separate connected apps page', function () {
    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);

    config(['picstome.admin_emails' => ['admin@example.com']]);

    actingAs($admin)
        ->get('/settings/password')
        ->assertOk()
        ->assertDontSee(route('settings.apps'), false);
});

test('the danger zone is visible to every user', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get('/settings/password')
        ->assertOk()
        ->assertSee('Danger zone')
        ->assertSee('Delete account');
});

test('lists only the current user sync tokens', function () {
    config([
        'picstome.admin_emails' => ['admin@example.com'],
        'picstome.sync_feature_admin_only' => false,
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $otherUser = User::factory()->withPersonalTeam()->create();

    $user->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));
    $otherToken = $otherUser->createToken('picstome-sync-def456', ['sync'], now()->addDays(365));
    $unrelated = $user->createToken('other-app-ghi789', ['read'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->assertSee('Connected apps')
        ->assertSee('picstome-sync-abc123')
        ->assertSee('Active')
        ->assertSee('Scope')
        ->assertSee('sync')
        ->assertDontSee($otherToken->accessToken->name)
        ->assertDontSee($unrelated->accessToken->name);
});

test('marks expired tokens', function () {
    config(['picstome.sync_feature_admin_only' => false]);

    $user = User::factory()->withPersonalTeam()->create();

    $user->createToken('picstome-sync-expired', ['sync'], now()->subDay());

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->assertSee('picstome-sync-expired')
        ->assertSee('Expired');
});

test('hides connected apps from non admins while the feature is admin only', function () {
    config([
        'picstome.admin_emails' => ['admin@example.com'],
        'picstome.sync_feature_admin_only' => true,
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $user->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->assertDontSee('Connected apps')
        ->assertDontSee('picstome-sync-abc123');
});

test('shows connected apps to admins while the feature is admin only', function () {
    config([
        'picstome.admin_emails' => ['admin@example.com'],
        'picstome.sync_feature_admin_only' => true,
    ]);

    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
    $admin->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));

    Livewire::actingAs($admin)
        ->test('pages::settings.password')
        ->assertSee('Connected apps')
        ->assertSee('picstome-sync-abc123');
});

test('revokes the current user token', function () {
    config(['picstome.sync_feature_admin_only' => false]);

    $user = User::factory()->withPersonalTeam()->create();
    $token = $user->createToken('picstome-sync-abc123', ['sync'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->call('revoke', $token->accessToken->id)
        ->assertSee('No connected apps yet');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

test('cannot revoke another user token', function () {
    config(['picstome.sync_feature_admin_only' => false]);

    $user = User::factory()->withPersonalTeam()->create();
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherToken = $otherUser->createToken('picstome-sync-other', ['sync'], now()->addDays(365));

    Livewire::actingAs($user)
        ->test('pages::settings.password')
        ->call('revoke', $otherToken->accessToken->id);

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherToken->accessToken->id,
    ]);
});
