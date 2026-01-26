<?php

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
});

test('visitors can view unlock shared gallery page when gallery is protected', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/unlock');

    $response->assertStatus(200);
});

test('protected gallery can be unlocked with correct password', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create(['ulid' => '0123ABC']);
    get('shares/0123ABC/'.$gallery->slug)->assertRedirect('/shares/0123ABC/unlock');

    $component = Livewire::test('pages.shares.unlock', ['gallery' => $gallery])
        ->set('password', 'secret')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_ulid'))->toBe('0123ABC');
    $component->assertRedirect('/shares/0123ABC/'.$gallery->slug);
    get('shares/0123ABC/'.$gallery->slug)->assertStatus(200);
});

test('protected gallery remains locked with incorrect password', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create(['ulid' => '0123ABC']);

    $component = Livewire::test('pages.shares.unlock', ['gallery' => $gallery])
        ->set('password', 'incorrect-password')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_id'))->toBeNull();
    get('shares/0123ABC/'.$gallery->slug)->assertRedirect('/shares/0123ABC/unlock');
});

test('visitor is redirected to shared gallery when it\'s not password protected', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/unlock');

    $response->assertRedirect('/shares/0123ABC/'.$gallery->slug);
});
