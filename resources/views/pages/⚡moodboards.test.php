<?php

use App\Models\Moodboard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team moodboards', function () {
    $moodboardA = Moodboard::factory()->for($this->team)->create();
    $moodboardB = Moodboard::factory()->for(Team::factory())->create();
    $moodboardC = Moodboard::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/moodboards');
    $component = Livewire::test('pages::moodboards');

    $response->assertStatus(200);
    expect($component->moodboards->count())->toBe(2);
    expect($component->moodboards->contains($moodboardA))->toBeTrue();
    expect($component->moodboards->contains($moodboardB))->toBeFalse();
    expect($component->moodboards->contains($moodboardC))->toBeTrue();
});

test('can create a team moodboard', function () {
    $component = Livewire::actingAs($this->user)->test('pages::moodboards')
        ->set('form.title', 'My Moodboard')
        ->call('save');

    $component->assertRedirect('/moodboards/1');
    expect($this->team->moodboards()->count())->toBe(1);
    expect($this->team->moodboards()->first()->title)->toBe('My Moodboard');
});

test('can create a team moodboard with description', function () {
    $component = Livewire::actingAs($this->user)->test('pages::moodboards')
        ->set('form.title', 'My Moodboard')
        ->set('form.description', 'This is a test moodboard')
        ->call('save');

    $component->assertRedirect('/moodboards/1');
    expect($this->team->moodboards()->count())->toBe(1);
    expect($this->team->moodboards()->first()->title)->toBe('My Moodboard');
    expect($this->team->moodboards()->first()->description)->toBe('This is a test moodboard');
});

test('cannot create a moodboard without a title', function () {
    $component = Livewire::actingAs($this->user)->test('pages::moodboards')
        ->set('form.title', '')
        ->call('save');

    $component->assertHasErrors(['form.title' => 'required']);
    expect($this->team->moodboards()->count())->toBe(0);
});
