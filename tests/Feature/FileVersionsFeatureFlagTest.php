<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    config(['picstome.file_versions_enabled' => false]);
});

it('hides the owner version UI when disabled', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 2]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
        'disk' => 's3',
    ]);

    actingAs($user)
        ->get(route('galleries.photos.show', [$gallery, $photo]))
        ->assertOk()
        ->assertDontSee('Compare versions')
        ->assertDontSee('Versions (');
});

it('hides version comparison in shared galleries when disabled', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'is_share_versions_visible' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 2]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
        'disk' => 's3',
    ]);

    get('/shares/0123ABC/photos/1')
        ->assertOk()
        ->assertDontSee('Compare versions');
});

it('blocks creating versions at the model layer when disabled', function () {
    $gallery = Gallery::factory()->create();
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 1]);

    expect(fn () => $gallery->addVersion(
        UploadedFile::fake()->image('photo1.jpg'),
        $photo,
    ))->toThrow(RuntimeException::class, 'File versions are currently disabled.');
});

it('does not persist the shared versions option when disabled', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('shareForm.versionsVisible', true)
        ->call('share')
        ->assertHasNoErrors();

    expect($gallery->fresh()->is_share_versions_visible)->toBeFalse();
});
