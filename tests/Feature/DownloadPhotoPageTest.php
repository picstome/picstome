<?php

use App\Models\Gallery;
use App\Models\Photo;
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

test('can download a photo', function () {
    Storage::fake('s3');

    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 's3'),
    ]);

    $response = actingAs($this->user)->get('galleries/1/photos/1/download');

    $response->assertDownload('photo1.jpg');
});

test('guests cannot download any galleries', function () {
    Photo::factory()->create();

    $response = get('/galleries/1/photos/1/download');

    $response->assertRedirect('/login');
});

test('users cannot download the team gallery photos of other users', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1/download');

    $response->assertStatus(403);
});
