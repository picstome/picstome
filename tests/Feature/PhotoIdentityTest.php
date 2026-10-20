<?php

use App\Events\PhotoAdded;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('uses the same identity for jpg, jpeg and raw files with the same stem', function () {
    expect(Gallery::identityKey('IMG_0001.JPG'))
        ->toBe(Gallery::identityKey('img_0001.jpeg'))
        ->toBe(Gallery::identityKey('IMG_0001.CR3'));
});

it('keeps image, video and pdf families separate', function () {
    expect(Gallery::identityKey('IMG_0001.jpg'))->not->toBe(Gallery::identityKey('IMG_0001.mp4'));
    expect(Gallery::identityKey('IMG_0001.jpg'))->not->toBe(Gallery::identityKey('IMG_0001.pdf'));
});

it('versions an upload with a different image extension or case', function () {
    Storage::fake('s3');

    Event::fake(PhotoAdded::class);

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();

    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'IMG_0001.CR3',
        'source_name' => 'IMG_0001.CR3',
        'path' => 'old/IMG_0001.CR3',
        'size' => 1024,
        'version_count' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('photos.0', UploadedFile::fake()->image('img_0001.jpeg'))
        ->call('save', 0)
        ->assertHasNoErrors();

    $photo->refresh();

    expect($gallery->photos()->count())->toBe(1);
    expect($photo->version_count)->toBe(2);
    expect($photo->source_name)->toBe('img_0001.jpeg');
    expect($photo->versions()->count())->toBe(1);
});

it('detects duplicate upload names using the normalized identity', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();

    Photo::factory()->for($gallery)->create([
        'name' => 'IMG_0001.CR3',
        'source_name' => 'IMG_0001.CR3',
    ]);

    Livewire::actingAs($user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->call('findDuplicateNames', ['img_0001.jpeg', 'OTHER.jpg'])
        ->assertSet('existingPhotoNames', ['img_0001.jpeg']);
});

it('versions a raw upload over an existing jpg with the same stem', function () {
    Storage::fake('s3');

    Event::fake(PhotoAdded::class);

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();

    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'IMG_0002.JPG',
        'source_name' => 'IMG_0002.JPG',
        'path' => 'old/IMG_0002.JPG',
        'size' => 1024,
        'version_count' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('photos.0', UploadedFile::fake()->create('IMG_0002.CR3', 1024))
        ->call('save', 0)
        ->assertHasNoErrors();

    $photo->refresh();

    expect($gallery->photos()->count())->toBe(1);
    expect($photo->version_count)->toBe(2);
    expect($photo->source_name)->toBe('IMG_0002.CR3');
    expect($photo->versions()->count())->toBe(1);
});

it('normalizes names with multiple dots and mixed case', function () {
    expect(Gallery::identityKey('Session.01.Final.JPEG'))
        ->toBe(Gallery::identityKey('session.01.final.jpg'));
});
