<?php

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('generates handles for teams without handles', function () {
    $team1 = Team::factory()->create(['handle' => null, 'name' => 'John\'s Studio']);
    $team2 = Team::factory()->create(['handle' => null, 'name' => 'Jane\'s Photography']);

    artisan('teams:backfill-handles')
        ->assertSuccessful();

    $team1->refresh();
    $team2->refresh();

    expect($team1->handle)->not->toBeNull();
    expect($team2->handle)->not->toBeNull();
    expect($team1->handle)->toBe('johnsstudio');
    expect($team2->handle)->toBe('janesphotography');
});

it('skips teams that already have handles', function () {
    $existingHandle = 'existinghandle';

    $team = Team::factory()->create(['handle' => $existingHandle]);

    artisan('teams:backfill-handles')
        ->assertSuccessful();

    $team->refresh();
    expect($team->handle)->toBe($existingHandle);
});

it('handles duplicate generated handles', function () {
    $team1 = Team::factory()->create(['handle' => null, 'name' => 'Test Team']);
    $team2 = Team::factory()->create(['handle' => null, 'name' => 'Test Team']);

    artisan('teams:backfill-handles')
        ->assertSuccessful();

    $team1->refresh();
    $team2->refresh();

    expect($team1->handle)->not->toBe($team2->handle);
    expect($team1->handle)->toBe('testteam');
    expect($team2->handle)->toBe('testteam1');
});

it('outputs progress information', function () {
    Team::factory()->create(['handle' => null]);

    artisan('teams:backfill-handles')
        ->expectsOutput('Backfilling handles for existing teams...')
        ->expectsOutput('Processed 1 teams')
        ->expectsOutput('Backfill completed successfully')
        ->assertSuccessful();
});

it('handles empty team list', function () {
    artisan('teams:backfill-handles')
        ->expectsOutput('Backfilling handles for existing teams...')
        ->expectsOutput('No teams found that need handles')
        ->expectsOutput('Backfill completed successfully')
        ->assertSuccessful();
});
