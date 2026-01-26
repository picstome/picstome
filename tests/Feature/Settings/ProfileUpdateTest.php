<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('profile page is displayed', function () {
    actingAs($user = User::factory()->withPersonalTeam()->create());

    get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = Livewire::actingAs($user)->test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('users can upload an avatar', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();

    $response = Livewire::actingAs($user)->test('pages::settings.profile')
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg'))
        ->call('updateProfileInformation');

    Storage::disk('s3')->assertCount('/avatars', 1);

    tap($user->fresh(), function (User $user) {
        expect($user->avatar_path)->not->toBeNull();
        expect($user->avatar_url)->not->toBeNull();
    });
});

test('users can delete their avatar', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();

    $user->updateAvatar(UploadedFile::fake()->image('avatar.jpg'));

    Storage::disk('s3')->assertCount('/avatars', 1);

    $response = Livewire::actingAs($user)->test('pages::settings.profile')
        ->call('deleteAvatar');

    Storage::disk('s3')->assertCount('/avatars', 0);

    tap($user->fresh(), function (User $user) {
        expect($user->avatar_path)->toBeNull();
    });
});
