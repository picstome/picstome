<?php

use App\Models\Team;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows only payments belonging to the users team', function () {
    $paymentA = Payment::factory()->for($this->team)->create();
    $paymentB = Payment::factory()->for(Team::factory())->create();
    $paymentC = Payment::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/payments');
    $component = Volt::test('pages.payments');

    $response->assertStatus(200);
    expect($component->payments->count())->toBe(2);
    expect($component->payments->contains($paymentA))->toBeTrue();
    expect($component->payments->contains($paymentB))->toBeFalse();
    expect($component->payments->contains($paymentC))->toBeTrue();
});
