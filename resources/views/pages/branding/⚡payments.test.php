<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = \App\Models\User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('allows users to see the payments branding page', function () {
    $response = \Pest\Laravel\actingAs($this->user)->get('/branding/payments');

    $response->assertStatus(200);
});
