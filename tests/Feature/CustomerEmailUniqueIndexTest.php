<?php

use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('a second customer with the same email on the same team is rejected by the database', function () {
    $team = Team::factory()->create();
    Customer::factory()->for($team)->create(['email' => 'c@x.com']);

    expect(fn () => Customer::create([
        'team_id' => $team->id,
        'name' => 'Cristina Requena',
        'email' => 'c@x.com',
    ]))->toThrow(QueryException::class);
});

it('customers with null emails can coexist on the same team', function () {
    $team = Team::factory()->create();
    Customer::factory()->for($team)->create(['email' => null]);
    Customer::factory()->for($team)->create(['email' => null]);

    expect(Customer::whereNull('email')->count())->toBe(2);
});

it('the same email on different teams is allowed', function () {
    $firstTeam = Team::factory()->create();
    $secondTeam = Team::factory()->create();
    Customer::factory()->for($firstTeam)->create(['email' => 'c@x.com']);
    Customer::factory()->for($secondTeam)->create(['email' => 'c@x.com']);

    expect(Customer::where('email', 'c@x.com')->count())->toBe(2);
});
