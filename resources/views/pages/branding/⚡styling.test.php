<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = \App\Models\User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('allows users to see the styling branding page', function () {
    $response = actingAs($this->user)->get('/branding/styling');

    $response->assertStatus(200);
});

it('can change brand color', function () {
    expect($this->team->brand_color)->not()->toBe('red');

    Livewire::actingAs($this->user)->test('pages::branding.styling')
        ->set('form.color', 'red')
        ->call('save');

    expect($this->team->fresh()->brand_color)->toBe('red');
});

it('can change brand font', function () {
    expect($this->team->brand_font)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.styling')
        ->set('form.font', 'Montserrat')
        ->call('save');

    expect($this->team->fresh()->brand_font)->toBe('Montserrat');
});
