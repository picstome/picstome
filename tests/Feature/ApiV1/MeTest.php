<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns the profile photo url when the user uploaded one', function () {
    Storage::fake(config('picstome.disk', 'public'));
    $user = User::factory()->create();
    $user->updateAvatar(UploadedFile::fake()->image('me.jpg'));
    Sanctum::actingAs($user, ['sync']);

    $response = $this->getJson('/api/v1/me')->assertOk();

    expect($response->json('avatar_url'))
        ->toBeString()
        ->toBe($user->fresh()->avatar_url);
});

it('returns no avatar url for the generated initials avatar', function () {
    Sanctum::actingAs(User::factory()->create(), ['sync']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('avatar_url', null);
});
