<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('switches to a team owned by the user', function () {
    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();

    expect($user->switchTeam($team))->toBeTrue();
    expect($user->fresh()->current_team_id)->toBe($team->id);
});

it('rejects switching to a team the user does not own', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    expect(fn () => $user->switchTeam($team))->toThrow(HttpException::class);
});
