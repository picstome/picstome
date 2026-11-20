<?php

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('stores uploads on the configured photo disk', function () {
    config()->set('picstome.disk', 'public');
    Storage::fake('public');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    expect($photo->disk)->toBe('public')
        ->and(Storage::disk('public')->exists($photo->path))->toBeTrue();
});

it('keeps replacement versions on the configured photo disk', function () {
    config()->set('picstome.disk', 'public');
    Storage::fake('public');

    $gallery = Gallery::factory()->for($this->team)->create();
    $existing = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $updated = $gallery->addVersion(UploadedFile::fake()->image('photo2.jpg'), $existing);

    expect($updated->disk)->toBe('public')
        ->and(Storage::disk('public')->exists($updated->path))->toBeTrue();
});
