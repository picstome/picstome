<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view a team photo in the gallery', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertStatus(200);
    $response->assertViewHas('photo');
    expect($response['photo']->is($photo))->toBeTrue();
});

test('guests cannot view any photos', function () {
    $photo = Photo::factory()->create();

    Gallery::factory()->for($this->team)->create();

    $response = get('/galleries/1/photos/1');

    $response->assertRedirect('/login');
});

test('users cannot view the team gallery photos of other users', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertStatus(403);
});

test('can delete a photo', function () {
    Storage::fake('public');
    $photo = Photo::factory()->create([
        'name' => 'photo1.jpg',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->storeAs('galleries/1/photos', 'photo1.jpg', 'public'),
        'thumb_path' => UploadedFile::fake()
            ->image('photo1_thumb.jpg')
            ->storeAs('galleries/1/photos', 'photo1_thumb.jpg', 'public'),

    ]);
    Storage::disk('public')->assertExists(['galleries/1/photos/photo1.jpg']);
    expect(Photo::count())->toBe(1);

    $component = Volt::test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('delete');

    $component->assertRedirect('/galleries/1');
    expect(Photo::count())->toBe(0);
    Storage::disk('public')->assertMissing(['galleries/1/photos/photo1.jpg']);
    Storage::disk('public')->assertMissing(['galleries/1/photos/photo1_thumb.jpg']);
});

test('can favorite a photo', function () {
    $photo = Photo::factory()->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Volt::test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeTrue();
});
