<?php

use App\Models\Contract;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the search component successfully', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user);

    $component = Volt::test('search');

    $component->assertOk();
});

it('returns galleries that match the search query', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Gallery::factory()->for($team)->create(['name' => 'Wedding Photos']);
    Gallery::factory()->for($team)->create(['name' => 'Birthday Party']);
    Gallery::factory()->for($team)->create(['name' => 'Corporate Event']);

    actingAs($user);

    $component = Volt::test('search')
        ->set('search', 'Wedding');

    $component->assertSet('galleries', function ($galleries) {
        return $galleries->count() === 1 && $galleries->first()->name === 'Wedding Photos';
    });
});

it('returns photoshoots that match the search query', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Photoshoot::factory()->for($team)->create(['name' => 'Summer Wedding']);
    Photoshoot::factory()->for($team)->create(['name' => 'Birthday Celebration']);
    Photoshoot::factory()->for($team)->create(['name' => 'Corporate Headshots']);

    actingAs($user);

    $component = Volt::test('search')
        ->set('search', 'Wedding');

    $component->assertSet('photoshoots', function ($photoshoots) {
        return $photoshoots->count() === 1 && $photoshoots->first()->name === 'Summer Wedding';
    });
});

it('returns contracts that match the search query', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Contract::factory()->for($team)->create(['title' => 'Wedding Contract']);
    Contract::factory()->for($team)->create(['title' => 'Birthday Contract']);
    Contract::factory()->for($team)->create(['title' => 'Corporate Contract']);

    actingAs($user);

    $component = Volt::test('search')
        ->set('search', 'Wedding');

    $component->assertSet('contracts', function ($contracts) {
        return $contracts->count() === 1 && $contracts->first()->title === 'Wedding Contract';
    });
});

it('returns photos that match the search query', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $gallery1 = Gallery::factory()->for($team)->create(['name' => 'Gallery 1']);
    $gallery2 = Gallery::factory()->for($team)->create(['name' => 'Gallery 2']);

    Photo::factory()->for($gallery1)->create(['name' => 'wedding-photo.jpg']);
    Photo::factory()->for($gallery1)->create(['name' => 'portrait.jpg']);
    Photo::factory()->for($gallery2)->create(['name' => 'wedding-ceremony.jpg']);

    actingAs($user);

    $component = Volt::test('search')
        ->set('search', 'wedding');

    $component->assertSet('photos', function ($photos) {
        return $photos->count() === 2 &&
               $photos->pluck('name')->sort()->values()->all() === ['wedding-ceremony.jpg', 'wedding-photo.jpg'];
    });
});

it('returns empty results when no items match the search query', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Gallery::factory()->for($team)->create(['name' => 'Birthday Party']);
    Photoshoot::factory()->for($team)->create(['name' => 'Corporate Event']);
    Contract::factory()->for($team)->create(['title' => 'Business Contract']);

    actingAs($user);

    $component = Volt::test('search')
        ->set('search', 'wedding');

    $component->assertSet('galleries', fn($g) => $g->isEmpty());
    $component->assertSet('photoshoots', fn($p) => $p->isEmpty());
    $component->assertSet('contracts', fn($c) => $c->isEmpty());
    $component->assertSet('photos', fn($p) => $p->isEmpty());
});

it('redirects to the correct gallery page when viewGallery is called', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $gallery = Gallery::factory()->for($team)->create();

    actingAs($user);

    $component = Volt::test('search')
        ->call('viewGallery', $gallery->id);

    $component->assertRedirect("/galleries/{$gallery->id}");
});

it('redirects to the correct photoshoot page when viewPhotoshoot is called', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $photoshoot = Photoshoot::factory()->for($team)->create();

    actingAs($user);

    $component = Volt::test('search')
        ->call('viewPhotoshoot', $photoshoot->id);

    $component->assertRedirect("/photoshoots/{$photoshoot->id}");
});

it('redirects to the correct contract page when viewContract is called', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $contract = Contract::factory()->for($team)->create();

    actingAs($user);

    $component = Volt::test('search')
        ->call('viewContract', $contract->id);

    $component->assertRedirect("/contracts/{$contract->id}");
});

it('redirects to the correct photo page when viewPhoto is called', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $gallery = Gallery::factory()->for($team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    actingAs($user);

    $component = Volt::test('search')
        ->call('viewPhoto', $gallery->id, $photo->id);

    $component->assertRedirect("/galleries/{$gallery->id}/photos/{$photo->id}");
});

it('scopes search results to the current user\'s team only', function () {
    $user1 = User::factory()->withPersonalTeam()->create();
    $user2 = User::factory()->withPersonalTeam()->create();

    $team1 = $user1->currentTeam;
    $team2 = $user2->currentTeam;

    Gallery::factory()->for($team1)->create(['name' => 'Team1 Gallery']);
    Gallery::factory()->for($team2)->create(['name' => 'Team1 Gallery']);

    actingAs($user1);

    $component = Volt::test('search')
        ->set('search', 'Team1');

    $component->assertSet('galleries', function ($galleries) use ($team1) {
        return $galleries->count() === 1 && $galleries->first()->team_id === $team1->id;
    });
});
