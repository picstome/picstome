<?php

use App\Models\Customer;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('narrows customers to the next thirty days of birthdays', function () {
    $this->travelTo(Carbon::parse('2026-06-15 10:00:00'));

    $team = Team::factory()->create();

    $today = Customer::factory()->for($team)->create(['birthdate' => Carbon::parse('1990-06-15')]);
    $soon = Customer::factory()->for($team)->create(['birthdate' => Carbon::parse('1990-06-25')]);
    $late = Customer::factory()->for($team)->create(['birthdate' => Carbon::parse('1990-07-20')]);
    $yesterday = Customer::factory()->for($team)->create(['birthdate' => Carbon::parse('1990-06-14')]);

    $ids = $team->customers()->birthdaySoon()->pluck('id');

    expect($ids)->toContain($today->id, $soon->id)
        ->and($ids)->not->toContain($late->id, $yesterday->id);
});

it('includes birthdays that wrap around the end of the year', function () {
    $this->travelTo(Carbon::parse('2026-12-20 10:00:00'));

    $team = Team::factory()->create();

    $newYear = Customer::factory()->for($team)->create(['birthdate' => Carbon::parse('1990-01-05')]);
    $far = Customer::factory()->for($team)->create(['birthdate' => Carbon::parse('1990-03-05')]);

    $ids = $team->customers()->birthdaySoon()->pluck('id');

    expect($ids)->toContain($newYear->id)
        ->and($ids)->not->toContain($far->id);
});
