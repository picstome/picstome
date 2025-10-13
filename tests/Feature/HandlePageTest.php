<?php

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('displays team name for valid handle', function () {
    $team = Team::factory()->create(['handle' => 'testuser', 'name' => 'Test User Studio']);

    get('/@testuser')
        ->assertStatus(200)
        ->assertSee('Test User Studio');
});

it('returns 404 for non-existent handle', function () {
    get('/@nonexistent')
        ->assertStatus(404);
});

it('is case insensitive', function () {
    $team = Team::factory()->create(['handle' => 'testuser', 'name' => 'Test User Studio']);

    get('/@TestUser')
        ->assertStatus(200)
        ->assertSee('Test User Studio');
});

it('handles handle with numbers', function () {
    $team = Team::factory()->create(['handle' => 'user123', 'name' => 'User 123 Studio']);

    get('/@user123')
        ->assertStatus(200)
        ->assertSee('User 123 Studio');
});

it('handles handle with underscores', function () {
    $team = Team::factory()->create(['handle' => 'test_user', 'name' => 'Test User Studio']);

    get('/@test_user')
        ->assertStatus(200)
        ->assertSee('Test User Studio');
});

it('shows team profile page for all users', function () {
    $team = Team::factory()->create(['handle' => 'publicteam', 'name' => 'Public Team']);

    get('/@publicteam')
        ->assertStatus(200)
        ->assertSee('Public Team');
});

it('handles empty handle', function () {
    get('/@')
        ->assertStatus(404);
});

it('handles handle with special characters', function () {
    get('/@test@user')
        ->assertStatus(404);
});

it('generates payment link with valid data', function () {
    $team = Team::factory()->create(['handle' => 'publicteam', 'name' => 'Public Team']);

    $component = Volt::test('pages.handle.show', ['handle' => $team->handle])
        ->set('amount', 1500)
        ->set('description', 'Test public payment')
        ->call('generatePaymentLink');

    $expectedUrl = route('handle.pay', [
        'handle' => $team->handle,
        'amount' => 1500,
        'description' => 'Test public payment',
    ]);

    $component->assertRedirect($expectedUrl);
});

it('fails validation if amount is missing', function () {
    $team = Team::factory()->create(['handle' => 'publicteam', 'name' => 'Public Team']);

    Volt::test('pages.handle.show', ['handle' => $team->handle])
        ->set('description', 'Test public payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['amount' => 'required']);
});

it('fails validation if amount is not integer or < 1', function () {
    $team = Team::factory()->create(['handle' => 'publicteam', 'name' => 'Public Team']);

    $component = Volt::test('pages.handle.show', ['handle' => $team->handle])
        ->set('amount', 0)
        ->set('description', 'Test public payment')
        ->call('generatePaymentLink')
        ->assertHasErrors(['amount' => 'min']);

    $component->set('amount', 'abc')
        ->call('generatePaymentLink')
        ->assertHasErrors(['amount' => 'integer']);
});

it('fails validation if description is missing', function () {
    $team = Team::factory()->create(['handle' => 'publicteam', 'name' => 'Public Team']);

    Volt::test('pages.handle.show', ['handle' => $team->handle])
        ->set('amount', 1500)
        ->call('generatePaymentLink')
        ->assertHasErrors(['description' => 'required']);
});

it('fails validation if description is too long', function () {
    $team = Team::factory()->create(['handle' => 'publicteam', 'name' => 'Public Team']);

    Volt::test('pages.handle.show', ['handle' => $team->handle])
        ->set('amount', 1500)
        ->set('description', str_repeat('a', 256))
        ->call('generatePaymentLink')
        ->assertHasErrors(['description' => 'max']);
});
