<?php

use App\Models\ExternalAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function authorizeQuery(array $overrides = []): array
{
    return array_merge([
        'nonce' => str_repeat('n', 43),
        'client_name' => 'picstome-sync',
    ], $overrides);
}

it('redirects guests to login and preserves the authorization url', function () {
    $this->get('/authorize?'.http_build_query(authorizeQuery()))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toContain('/authorize?');
});

it('shows the consent screen with account, scope and nonce prefix', function () {
    $user = User::factory()->create();
    $nonce = str_repeat('a', 43);

    $this->actingAs($user)
        ->get('/authorize?'.http_build_query(authorizeQuery(['nonce' => $nonce])))
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('picstome-sync')
        ->assertSee(substr($nonce, 0, 8))
        ->assertSee('View manifests, download, upload and delete photos in your galleries.');
});

it('rejects invalid nonce and client name with a 422 view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/authorize?'.http_build_query(authorizeQuery(['nonce' => 'too-short'])))
        ->assertStatus(422)
        ->assertSee('Back to Picstome');

    $this->actingAs($user)
        ->get('/authorize?'.http_build_query(authorizeQuery(['client_name' => 'bad$name'])))
        ->assertStatus(422);
});

it('creates a single-use code and redirects to the client deep link', function () {
    $user = User::factory()->create();
    $nonce = str_repeat('b', 43);

    $response = $this->actingAs($user)->post('/authorize', authorizeQuery(['nonce' => $nonce]));

    $response->assertRedirect();
    $location = $response->headers->get('Location');

    expect($location)->toStartWith('picstome://auth?code=');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['state'])->toBe($nonce)
        ->and($query['code'])->toHaveLength(48);

    $authorization = ExternalAuthorization::query()->firstOrFail();

    expect($authorization->user_id)->toBe($user->id)
        ->and($authorization->client_name)->toBe('picstome-sync')
        ->and($authorization->nonce)->toBe($nonce)
        ->and($authorization->code_hash)->toBe(hash('sha256', $query['code']))
        ->and($authorization->code_hash)->not->toBe($query['code'])
        ->and($authorization->consumed_at)->toBeNull()
        ->and($authorization->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(5);
});

it('renders the close-window message when the user cancels', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('external.authorize.cancel'))
        ->assertOk()
        ->assertSee('You can close this window.')
        ->assertSee('Back to Picstome')
        ->assertSeeHtml('href="'.route('dashboard').'"');
});
