<?php

use App\Models\Moodboard;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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
    $component = Volt::test('pages.moodboards');

    $response->assertStatus(200);
    expect($component->moodboards->count())->toBe(2);
    expect($component->moodboards->contains($moodboardA))->toBeTrue();
    expect($component->moodboards->contains($moodboardB))->toBeFalse();
    expect($component->moodboards->contains($moodboardC))->toBeTrue();
});

test('can create a team moodboard', function () {
    $component = Volt::actingAs($this->user)->test('pages.moodboards')
        ->set('form.name', 'Summer Collection')
        ->set('form.description', 'Inspiration for summer shoot')
        ->set('form.isPublic', false)
        ->call('save');

    $component->assertRedirect('/moodboards/1');
    expect($this->team->moodboards()->count())->toBe(1);
    expect($this->team->moodboards()->first()->name)->toBe('Summer Collection');
    expect($this->team->moodboards()->first()->description)->toBe('Inspiration for summer shoot');
    expect($this->team->moodboards()->first()->is_public)->toBeFalse();
});

test('can create a public moodboard', function () {
    $component = Volt::actingAs($this->user)->test('pages.moodboards')
        ->set('form.name', 'Public Moodboard')
        ->set('form.isPublic', true)
        ->call('save');

    expect(Moodboard::first()->is_public)->toBeTrue();
});

test('can view a team moodboard', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/moodboards/1');

    $response->assertStatus(200);
    $response->assertViewHas('moodboard');
    expect($response['moodboard']->is($moodboard))->toBeTrue();
});

test('cannot view another team moodboard', function () {
    $moodboard = Moodboard::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/moodboards/1');

    $response->assertStatus(403);
});

test('can edit a team moodboard', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();

    $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
        ->set('form.name', 'Updated Moodboard')
        ->set('form.description', 'Updated description')
        ->call('update');

    tap($moodboard->fresh(), function (Moodboard $moodboard) {
        expect($moodboard->name)->toBe('Updated Moodboard');
        expect($moodboard->description)->toBe('Updated description');
    });
});

test('can delete a team moodboard', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();

    $component = Volt::actingAs($this->user)
        ->test('pages.moodboards.show', ['moodboard' => $moodboard])
        ->call('delete');

    $component->assertRedirect('/moodboards');
    expect(Moodboard::count())->toBe(0);
});

test('can add photos to moodboard', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();

    $gallery = \App\Models\Gallery::factory()->for($this->team)->create();
    $photoA = Photo::factory()->for($gallery)->create();
    $photoB = Photo::factory()->for($gallery)->create();

    $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
        ->set('selectedPhotos', [$photoA->id, $photoB->id])
        ->call('addSelectedPhotos');

    expect($moodboard->fresh()->photos->count())->toBe(2);
});

test('can remove photo from moodboard', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();

    $gallery = \App\Models\Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $moodboard->addPhoto($photo);

    $component = Volt::actingAs($this->user)
        ->test('pages.moodboards.show', ['moodboard' => $moodboard])
        ->call('removePhoto', $photo);

    expect($moodboard->fresh()->photos->count())->toBe(0);
});

test('can attach moodboard to photoshoot', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();
    $photoshoot = \App\Models\Photoshoot::factory()->for($this->team)->create();

    $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
        ->set('form.photoshoot_id', $photoshoot->id)
        ->call('update');

    expect($moodboard->fresh()->photoshoot_id)->toBe($photoshoot->id);
});

test('guests cannot view any moodboard', function () {
    $moodboard = Moodboard::factory()->for($this->team)->create();

    $response = get('/moodboards/1');

    $response->assertRedirect('/login');
});
