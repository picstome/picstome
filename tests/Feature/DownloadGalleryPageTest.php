<?php

use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('all gallery photos can be downloaded as a zip file', function () {
    Storage::fake('public');
    Storage::fake('s3');

    $photos = collect([
        UploadedFile::fake()->image('photo1.jpg'),
        UploadedFile::fake()->image('photo2.jpg'),
    ]);
    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $photos->each(function ($photo) use ($gallery) {
        $gallery->addPhoto($photo);
    });

    $response = actingAs($this->user)->get('galleries/1/download');

    $response->assertDownload('example-gallery.zip');
});

test('guests cannot download any galleries', function () {
    Gallery::factory()->for($this->team)->create();

    $response = get('/galleries/1/download');

    $response->assertRedirect('/login');
});

test('users cannot download the team gallery of others', function () {
    Gallery::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/galleries/1/download');

    $response->assertStatus(403);
});
