<?php

use App\Models\ExternalAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAuthorization(User $user, string $code, array $overrides = []): ExternalAuthorization
{
    return ExternalAuthorization::create(array_merge([
        'user_id' => $user->id,
        'client_name' => 'picstome-sync',
        'code_hash' => hash('sha256', $code),
        'nonce' => str_repeat('n', 43),
        'expires_at' => now()->addMinutes(5),
    ], $overrides));
}

it('exchanges a valid code for a working Sanctum token', function () {
    $user = User::factory()->create();
    $code = 'valid-code-'.str()->random(32);

    createAuthorization($user, $code);

    $response = $this->postJson('/api/v1/tokens/exchange', [
        'code' => $code,
        'client_name' => 'picstome-sync',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'authkey', 'email', 'expires_at'])
        ->assertJsonPath('email', $user->email);

    $token = $response->json('token');

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($response->json('authkey'))->toBe($token);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'email' => $user->email,
            'display_name' => $user->name,
        ]);

    expect(ExternalAuthorization::query()->firstOrFail()->consumed_at)->not->toBeNull();
});

it('rejects a reused code', function () {
    $user = User::factory()->create();
    $code = 'single-use-'.str()->random(32);

    createAuthorization($user, $code);

    $this->postJson('/api/v1/tokens/exchange', ['code' => $code, 'client_name' => 'picstome-sync'])
        ->assertOk();

    $this->postJson('/api/v1/tokens/exchange', ['code' => $code, 'client_name' => 'picstome-sync'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'invalid_code');
});

it('rejects an expired code with 410', function () {
    $user = User::factory()->create();
    $code = 'expired-'.str()->random(32);

    createAuthorization($user, $code, ['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/tokens/exchange', ['code' => $code, 'client_name' => 'picstome-sync'])
        ->assertStatus(410)
        ->assertJsonPath('code', 'expired_code');
});

it('rejects an unknown code and a mismatched client name', function () {
    $user = User::factory()->create();
    $code = 'known-'.str()->random(32);

    createAuthorization($user, $code);

    $this->postJson('/api/v1/tokens/exchange', ['code' => 'unknown', 'client_name' => 'picstome-sync'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'invalid_code');

    $this->postJson('/api/v1/tokens/exchange', ['code' => $code, 'client_name' => 'other-client'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'invalid_code');
});

it('revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('picstome-sync-abc123', ['sync'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/tokens/revoke')
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
    ]);

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});
