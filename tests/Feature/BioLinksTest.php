<?php

use App\Models\BioLink;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('displays bio links management page', function () {
    actingAs($user = User::factory()->withPersonalTeam()->create());

    get('/bio-links')->assertOk();
});

it('requires authentication for bio links management page', function () {
    get('/bio-links')->assertRedirect('/login');
});

it('allows users to add a new bio link', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('addForm.title', 'Twitter')
        ->set('addForm.url', 'https://twitter.com/testuser')
        ->call('addLink');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->bioLinks)->toHaveCount(1);
    expect($team->bioLinks->first()->title)->toEqual('Twitter');
    expect($team->bioLinks->first()->url)->toEqual('https://twitter.com/testuser');
});

it('allows users to update an existing bio link', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $bioLink = BioLink::factory()->for($team)->create([
        'title' => 'Old Title',
        'url' => 'https://old-url.com',
    ]);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('editLink', $bioLink->id)
        ->set('editForm.title', 'New Title')
        ->set('editForm.url', 'https://new-url.com')
        ->call('updateLink', $bioLink);

    $response->assertHasNoErrors();

    $bioLink->refresh();
    expect($bioLink->title)->toEqual('New Title');
    expect($bioLink->url)->toEqual('https://new-url.com');
});

it('prevents users from updating bio links for other teams', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherTeam = Team::factory()->create();

    $otherBioLink = BioLink::factory()->for($otherTeam)->create([
        'title' => 'Other Team Link',
        'url' => 'https://other-team.com',
    ]);

    actingAs($user);

    get('/bio-links')->assertOk();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('editLink', $otherBioLink->id);

    $response->assertForbidden();
});

it('allows users to delete a bio link', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $bioLink = BioLink::factory()->for($team)->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('deleteLink', $bioLink->id);

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->bioLinks)->toHaveCount(0);
    expect(BioLink::find($bioLink->id))->toBeNull();
});

it('allows users to reorder bio links', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $link1 = BioLink::factory()->for($team)->create(['order' => 1]);
    $link2 = BioLink::factory()->for($team)->create(['order' => 2]);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('reorderLinks', [
            ['id' => $link2->id, 'order' => 1],
            ['id' => $link1->id, 'order' => 2],
        ]);

    $response->assertHasNoErrors();

    $link1->refresh();
    $link2->refresh();

    expect($link1->order)->toEqual(2);
    expect($link2->order)->toEqual(1);
});

it('requires bio link title', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('addForm.title', '')
        ->set('addForm.url', 'https://example.com')
        ->call('addLink');

    $response->assertHasErrors(['addForm.title' => 'required']);
});

it('requires bio link url and validates format', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('addForm.title', 'Test Link')
        ->set('addForm.url', '')
        ->call('addLink');

    $response->assertHasErrors(['addForm.url' => 'required']);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('addForm.title', 'Test Link')
        ->set('addForm.url', 'invalid-url')
        ->call('addLink');

    $response->assertHasErrors(['addForm.url' => 'url']);
});

it('validates bio link title maximum length', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('addForm.title', str_repeat('a', 256))
        ->set('addForm.url', 'https://example.com')
        ->call('addLink');

    $response->assertHasErrors(['addForm.title' => 'max']);
});

it('displays bio links on public handle page', function () {
    $team = Team::factory()->create(['handle' => 'testuser']);

    BioLink::factory()->for($team)->create([
        'title' => 'Instagram',
        'url' => 'https://instagram.com/testuser',
        'order' => 1,
    ]);

    BioLink::factory()->for($team)->create([
        'title' => 'Website',
        'url' => 'https://testuser.com',
        'order' => 2,
    ]);

    get('/@testuser')
        ->assertOk()
        ->assertSee('Instagram')
        ->assertSee('Website')
        ->assertSee('https://instagram.com/testuser')
        ->assertSee('https://testuser.com');
});

it('orders bio links correctly on public page', function () {
    $team = Team::factory()->create(['handle' => 'testuser']);

    BioLink::factory()->for($team)->create([
        'title' => 'Third Link',
        'url' => 'https://third.com',
        'order' => 3,
    ]);

    BioLink::factory()->for($team)->create([
        'title' => 'First Link',
        'url' => 'https://first.com',
        'order' => 1,
    ]);

    BioLink::factory()->for($team)->create([
        'title' => 'Second Link',
        'url' => 'https://second.com',
        'order' => 2,
    ]);

    $response = get('/@testuser');

    $response->assertOk();

    $content = $response->getContent();
    $firstPos = strpos($content, 'First Link');
    $secondPos = strpos($content, 'Second Link');
    $thirdPos = strpos($content, 'Third Link');

    expect($firstPos)->toBeLessThan($secondPos);
    expect($secondPos)->toBeLessThan($thirdPos);
});

it('works without bio links on public handle page', function () {
    $team = Team::factory()->create(['handle' => 'testuser']);

    get('/@testuser')
        ->assertOk()
        ->assertDontSee('Bio Links');
});

it('prevents users from managing bio links for other teams', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherTeam = Team::factory()->create();

    $otherBioLink = BioLink::factory()->for($otherTeam)->create();

    actingAs($user);

    get('/bio-links')
        ->assertOk()
        ->assertDontSee($otherBioLink->title);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('deleteLink', $otherBioLink);

    $response->assertForbidden();
});

it('isolates bio links by team', function () {
    $user1 = User::factory()->withPersonalTeam()->create();
    $user2 = User::factory()->withPersonalTeam()->create();

    $team1 = $user1->currentTeam;
    $team2 = $user2->currentTeam;

    $team1->update(['handle' => 'team1']);
    $team2->update(['handle' => 'team2']);

    $link1 = BioLink::factory()->for($team1)->create(['title' => 'Team 1 Link']);
    $link2 = BioLink::factory()->for($team2)->create(['title' => 'Team 2 Link']);

    actingAs($user1);
    get('/@team1')
        ->assertOk()
        ->assertSee('Team 1 Link')
        ->assertDontSee('Team 2 Link');

    actingAs($user2);
    get('/@team2')
        ->assertOk()
        ->assertSee('Team 2 Link')
        ->assertDontSee('Team 1 Link');
});
