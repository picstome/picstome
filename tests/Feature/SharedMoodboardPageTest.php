<?php

use App\Models\Moodboard;
use App\Models\MoodboardPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

describe('Shared Moodboard Viewing', function () {
    it('allows guests to view a shared moodboard', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create(['is_shared' => true, 'title' => 'Test Slug']);
        $photoA = MoodboardPhoto::factory()->for($moodboard)->create();
        $photoB = MoodboardPhoto::factory()->for(Moodboard::factory())->create();
        $photoC = MoodboardPhoto::factory()->for($moodboard)->create();

        $response = get('/shared-moodboards/'.$moodboard->ulid.'/test-slug');

        $response->assertStatus(200);
    });

    it('redirects from old URL format to new URL format with slug', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create(['is_shared' => true, 'title' => 'My Slug']);

        $response = get('/shared-moodboards/'.$moodboard->ulid);

        $response->assertRedirect(route('shared-moodboards.show', ['moodboard' => $moodboard, 'slug' => 'my-slug']));
    });

    it('prevents guests from viewing non-shared moodboards', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create(['is_shared' => false, 'title' => 'Test Slug']);

        $response = get('/shared-moodboards/'.$moodboard->ulid.'/test-slug');

        $response->assertStatus(404);
    });

    it('returns 404 when slug does not match', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create(['is_shared' => true, 'title' => 'Correct Slug']);

        $response = get('/shared-moodboards/'.$moodboard->ulid.'/wrong-slug');

        $response->assertStatus(404);
    });

    it('prevents viewing non-existent moodboards', function () {
        $response = get('/shared-moodboards/0000000000000000/does-not-exist');

        $response->assertStatus(404);
    });
});

describe('Shared Moodboard Caching', function () {
    it('caches photos for shared moodboard', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create(['is_shared' => true, 'title' => 'Cached Test']);
        $photo = MoodboardPhoto::factory()->for($moodboard)->create(['name' => 'photo.jpg']);

        $response = get('/shared-moodboards/'.$moodboard->ulid.'/cached-test');

        $response->assertStatus(200);

        $cacheKey = "moodboard:{$moodboard->id}:photos";
        $cachedPhotos = \Illuminate\Support\Facades\Cache::get($cacheKey);

        expect($cachedPhotos)->not->toBeNull();
        expect($cachedPhotos->count())->toBe(1);
        expect($cachedPhotos->first()->name)->toBe('photo.jpg');
    });
});
