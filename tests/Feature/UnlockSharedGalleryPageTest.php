<?php

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
});

test('visitors can view the unlock shared gallery page when the gallery is protected', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/unlock');

    $response->assertStatus(200);
});

test('protected gallery can be unlocked with the correct password', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create(['ulid' => '0123ABC']);
    get('shares/0123ABC')->assertRedirect('/shares/0123ABC/unlock');

    $component = Volt::test('pages.shares.unlock', ['gallery' => $gallery])
        ->set('password', 'secret')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_ulid'))->toBe('0123ABC');
    $component->assertRedirect('/shares/0123ABC');
    get('shares/0123ABC')->assertStatus(200);
});

test('protected gallery remains locked with incorrect password', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create(['ulid' => '0123ABC']);

    $component = Volt::test('pages.shares.unlock', ['gallery' => $gallery])
        ->set('password', 'incorrect-password')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_id'))->toBeNull();
    get('shares/0123ABC')->assertRedirect('/shares/0123ABC/unlock');
});

test('visitor is redirected to the shared gallery when it\'s not password protected', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/unlock');

    $response->assertRedirect('/shares/0123ABC');
});
