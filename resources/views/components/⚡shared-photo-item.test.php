<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('favoriting the photo that reaches the selection limit dispatches the limit event', function () {
    Notification::fake();

    $gallery = Gallery::factory()->shared()->selectable(2)->create();
    Photo::factory()->for($gallery)->favorited()->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    Livewire::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite')
        ->assertDispatched('selection-limit-reached');
});

test('favoriting a photo beyond the selection limit does not dispatch the limit event', function () {
    $gallery = Gallery::factory()->shared()->selectable(2)->create();
    Photo::factory()->count(2)->for($gallery)->favorited()->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    Livewire::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite')
        ->assertNotDispatched('selection-limit-reached');

    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('unfavoriting a photo at the selection limit does not dispatch the limit event', function () {
    $gallery = Gallery::factory()->shared()->selectable(2)->create();
    Photo::factory()->for($gallery)->favorited()->create();
    $photo = Photo::factory()->for($gallery)->favorited()->create();

    Livewire::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite')
        ->assertNotDispatched('selection-limit-reached');

    expect($photo->fresh()->isFavorited())->toBeFalse();
});

test('shared pdf photos render a static document tile without polling', function () {
    $photo = Photo::factory()->pdf()->for(Gallery::factory()->shared()->create())->create();

    $html = Livewire::test('shared-photo-item', ['photo' => $photo])->html();

    expect($html)->toContain('document.pdf');
    expect($html)->toContain('<svg');
    expect($html)->not->toContain('wire:poll');
    expect($html)->not->toContain('<video');
    expect($html)->not->toContain('animate-pulse');
});
