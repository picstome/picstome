<?php

use App\Models\BioLink;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('bio links management page is displayed', function () {
    actingAs($user = User::factory()->withPersonalTeam()->create());

    get('/bio-links')->assertOk();
});

test('bio links management page requires authentication', function () {
    get('/bio-links')->assertRedirect('/login');
});

test('users can add a new bio link', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->ownedTeams()->where('personal_team', true)->first();
    $user->current_team_id = $team->id;
    $user->save();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('title', 'Twitter')
        ->set('url', 'https://twitter.com/testuser')
        ->call('addLink');

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->bioLinks)->toHaveCount(1);
    expect($team->bioLinks->first()->title)->toEqual('Twitter');
    expect($team->bioLinks->first()->url)->toEqual('https://twitter.com/testuser');
});

test('users can update an existing bio link', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $bioLink = BioLink::factory()->for($team)->create([
        'title' => 'Old Title',
        'url' => 'https://old-url.com',
    ]);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('editingLink', $bioLink->id)
        ->set('title', 'New Title')
        ->set('url', 'https://new-url.com')
        ->call('updateLink');

    $response->assertHasNoErrors();

    $bioLink->refresh();
    expect($bioLink->title)->toEqual('New Title');
    expect($bioLink->url)->toEqual('https://new-url.com');
});

test('users can delete a bio link', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->ownedTeams()->where('personal_team', true)->first();
    $user->current_team_id = $team->id;
    $user->save();

    $bioLink = BioLink::factory()->for($team)->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('deleteLink', $bioLink->id);

    $response->assertHasNoErrors();

    $team->refresh();
    expect($team->bioLinks)->toHaveCount(0);
    expect(BioLink::find($bioLink->id))->toBeNull();
});

test('users can reorder bio links', function () {
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

test('bio link title is required', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('title', '')
        ->set('url', 'https://example.com')
        ->call('addLink');

    $response->assertHasErrors(['title' => 'required']);
});

test('bio link url is required and must be valid', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('title', 'Test Link')
        ->set('url', '')
        ->call('addLink');

    $response->assertHasErrors(['url' => 'required']);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('title', 'Test Link')
        ->set('url', 'invalid-url')
        ->call('addLink');

    $response->assertHasErrors(['url' => 'url']);
});

test('bio link title has maximum length', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->set('title', str_repeat('a', 256))
        ->set('url', 'https://example.com')
        ->call('addLink');

    $response->assertHasErrors(['title' => 'max']);
});

test('bio links are displayed on public handle page', function () {
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

test('bio links are ordered correctly on public page', function () {
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

test('public handle page works without bio links', function () {
    $team = Team::factory()->create(['handle' => 'testuser']);

    get('/@testuser')
        ->assertOk()
        ->assertDontSee('Bio Links');
});

test('users cannot manage bio links for teams they dont belong to', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $otherTeam = Team::factory()->create();

    $otherBioLink = BioLink::factory()->for($otherTeam)->create();

    actingAs($user);

    get('/bio-links')
        ->assertOk()
        ->assertDontSee($otherBioLink->title);

    $response = Volt::actingAs($user)->test('pages.bio-links')
        ->call('deleteLink', $otherBioLink->id);

    expect(BioLink::find($otherBioLink->id))->not->toBeNull();
});

test('bio links are isolated by team', function () {
    $team1 = Team::factory()->create(['handle' => 'team1']);
    $team2 = Team::factory()->create(['handle' => 'team2']);

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $team1->users()->attach($user1);
    $team2->users()->attach($user2);

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