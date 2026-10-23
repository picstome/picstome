<?php

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;

uses(RefreshDatabase::class);

it('limits free teams to one contract per month', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update([
        'monthly_contract_limit' => config('picstome.personal_team_monthly_contract_limit'),
    ]);

    expect(config('picstome.personal_team_monthly_contract_limit'))->toBe(1);
    expect($user->can('create', Contract::class))->toBeTrue();

    Contract::factory()->for($team)->create();

    expect($user->can('create', Contract::class))->toBeFalse();
});

it('does not count contracts from previous months against the limit', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update(['monthly_contract_limit' => 1]);

    Contract::factory()->for($team)->create([
        'created_at' => now()->subMonthNoOverflow()->startOfMonth(),
    ]);

    expect($user->can('create', Contract::class))->toBeTrue();
});

it('lets subscribed teams create contracts without the monthly limit', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update(['monthly_contract_limit' => 1]);

    Subscription::factory()->for($team, 'owner')->create();

    Contract::factory()->count(2)->for($team)->create();

    expect($user->can('create', Contract::class))->toBeTrue();
});

it('lets lifetime teams create contracts without the monthly limit', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $team->update([
        'monthly_contract_limit' => 1,
        'lifetime_at' => now()->subDay(),
    ]);

    Contract::factory()->count(2)->for($team)->create();

    expect($user->can('create', Contract::class))->toBeTrue();
});

it('backfills the old free plan limit without touching paid or customised teams', function () {
    $free = Team::factory()->create(['monthly_contract_limit' => 5]);
    $subscribed = Team::factory()->create(['monthly_contract_limit' => 5]);
    Subscription::factory()->for($subscribed, 'owner')->create();
    $lifetime = Team::factory()->create([
        'monthly_contract_limit' => 5,
        'lifetime_at' => now()->subDay(),
    ]);
    $custom = Team::factory()->create(['monthly_contract_limit' => 3]);

    $migration = require database_path('migrations/2026_10_23_053617_limit_free_plan_contracts_per_month.php');
    $migration->up();

    expect($free->fresh()->monthly_contract_limit)->toBe(1);
    expect($subscribed->fresh()->monthly_contract_limit)->toBe(5);
    expect($lifetime->fresh()->monthly_contract_limit)->toBe(5);
    expect($custom->fresh()->monthly_contract_limit)->toBe(3);

    $migration->up();

    expect($free->fresh()->monthly_contract_limit)->toBe(1);
});
