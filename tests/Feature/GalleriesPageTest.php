<?php

use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team galleries galleries', function () {
    $galleryA = Gallery::factory()->for($this->team)->create();
    $galleryB = Gallery::factory()->for(Team::factory())->create();
    $galleryC = Gallery::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/galleries');
    $component = Volt::test('pages.galleries');

    $response->assertStatus(200);
    $component->assertViewHas('galleries');
    expect($component->viewData('galleries')->contains($galleryA))->toBeTrue();
    expect($component->viewData('galleries')->contains($galleryB))->toBeFalse();
    expect($component->viewData('galleries')->contains($galleryC))->toBeTrue();
});

test('can create a team gallery', function () {
    $component = Volt::actingAs($this->user)->test('pages.galleries')
        ->set('form.name', 'Just a Photo Gallery')
        ->call('save');

    $component->assertRedirect('/galleries/1');
    expect($this->team->galleries()->count())->toBe(1);
    expect($this->team->galleries()->first()->name)->toBe('Just a Photo Gallery');
    expect($this->team->galleries()->first()->keep_original_size)->toBeFalse();
});

test('can create a team gallery that keeps photos at their original size', function () {
    $component = Volt::actingAs($this->user)->test('pages.galleries')
        ->set('form.name', 'Just a Photo Gallery')
        ->set('form.keepOriginalSize', true)
        ->call('save');

    expect(Gallery::count())->toBe(1);
    expect(Gallery::first()->name)->toBe('Just a Photo Gallery');
    expect(Gallery::first()->keep_original_size)->toBeTrue();
});

test('can create a team gallery with an expiration date', function () {
    $expiration = now()->addDays(7);

    $component = Volt::actingAs($this->user)->test('pages.galleries')
        ->set('form.name', 'Expiring Gallery')
        ->set('form.expirationDate', $expiration)
        ->call('save');

    $gallery = $this->team->galleries()->first();

    expect($gallery)->not->toBeNull();
    expect($gallery->name)->toBe('Expiring Gallery');
    expect($gallery->expiration_date->format('Y-m-d H:i:s'))->toBe($expiration->format('Y-m-d H:i:s'));
});

test('can update a gallery to set, change, and remove expiration date', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $newExpiration = now()->addDays(10);

    // Set expiration date
    $component = Volt::actingAs($this->user)->test('pages.galleries.edit', ['gallery' => $gallery])
        ->set('form.expirationDate', $newExpiration)
        ->call('update');
    $gallery->refresh();
    expect($gallery->expiration_date->eq($newExpiration))->toBeTrue();

    // Change expiration date
    $changedExpiration = now()->addDays(20);
    $component->set('form.expirationDate', $changedExpiration)->call('update');
    $gallery->refresh();
    expect($gallery->expiration_date->eq($changedExpiration))->toBeTrue();

    // Remove expiration date
    $component->set('form.expirationDate', null)->call('update');
    $gallery->refresh();
    expect($gallery->expiration_date)->toBeNull();
});

test('guests cannot create galleries', function () {
    $component = Volt::test('pages.galleries')->call('save');

    $component->assertStatus(403);
});
