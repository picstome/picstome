<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows the gallery team to download a version', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/version.jpg', 'version');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create([
        'path' => 'galleries/version.jpg',
        'disk' => 's3',
    ]);

    actingAs($user)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertOk();
});

it('forbids other teams from downloading a version', function () {
    Storage::fake('s3');

    $owner = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($owner->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create(['disk' => 's3']);

    $other = User::factory()->withPersonalTeam()->create();

    actingAs($other)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertForbidden();
});
