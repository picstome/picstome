<?php

use App\Models\Gallery;
use App\Models\Photoshoot;
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

    $component = Livewire::test('pages::shares.unlock', ['gallery' => $gallery])
        ->set('password', 'secret')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_ulids'))->toBe(['0123ABC']);
    $component->assertRedirect('/shares/0123ABC/'.$gallery->slug);
    get('shares/0123ABC/'.$gallery->slug)->assertStatus(200);
});

test('protected gallery remains locked with incorrect password', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create(['ulid' => '0123ABC']);

    $component = Livewire::test('pages::shares.unlock', ['gallery' => $gallery])
        ->set('password', 'incorrect-password')
        ->call('unlock');

    $component->assertHasErrors(['password' => trans('auth.failed')]);
    expect(session()->get('unlocked_gallery_ulids'))->toBeNull();
    get('shares/0123ABC/'.$gallery->slug)->assertRedirect('/shares/0123ABC/unlock');
});

test('visitor is redirected to shared gallery when it\'s not password protected', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/unlock');

    $response->assertRedirect('/shares/0123ABC/'.$gallery->slug);
});

test('unlocking a gallery also unlocks same-customer galleries sharing the password', function () {
    $photoshoot = Photoshoot::factory()->create();
    $galleryA = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create(['ulid' => 'AAA111']);
    $galleryB = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create(['ulid' => 'BBB222']);

    Livewire::test('pages::shares.unlock', ['gallery' => $galleryA])
        ->set('password', 'secret')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_ulids'))->toContain('AAA111', 'BBB222');
    get('shares/BBB222/'.$galleryB->slug)->assertStatus(200);
});

test('unlocking a gallery keeps different-password galleries locked', function () {
    $photoshoot = Photoshoot::factory()->create();
    $galleryA = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create(['ulid' => 'AAA111']);
    $galleryB = Gallery::factory()->shared()->protected(password: 'other')->for($photoshoot)->create(['ulid' => 'BBB222']);

    Livewire::test('pages::shares.unlock', ['gallery' => $galleryA])
        ->set('password', 'secret')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_ulids'))->toContain('AAA111');
    get('shares/BBB222/'.$galleryB->slug)->assertRedirect('/shares/BBB222/unlock');
});

test('unlocking a gallery does not unlock other customers galleries sharing the password', function () {
    $photoshoot = Photoshoot::factory()->create();
    $otherPhotoshoot = Photoshoot::factory()->create();
    $galleryA = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create(['ulid' => 'AAA111']);
    $galleryB = Gallery::factory()->shared()->protected(password: 'secret')->for($otherPhotoshoot)->create(['ulid' => 'BBB222']);

    Livewire::test('pages::shares.unlock', ['gallery' => $galleryA])
        ->set('password', 'secret')
        ->call('unlock');

    expect(session()->get('unlocked_gallery_ulids'))->toContain('AAA111');
    get('shares/BBB222/'.$galleryB->slug)->assertRedirect('/shares/BBB222/unlock');
});

test('unlocking a second gallery keeps the first unlocked', function () {
    $photoshoot = Photoshoot::factory()->create();
    $galleryA = Gallery::factory()->shared()->protected(password: 'secretA')->for($photoshoot)->create(['ulid' => 'AAA111']);
    $galleryB = Gallery::factory()->shared()->protected(password: 'secretB')->for($photoshoot)->create(['ulid' => 'BBB222']);

    Livewire::test('pages::shares.unlock', ['gallery' => $galleryA])
        ->set('password', 'secretA')
        ->call('unlock');

    Livewire::test('pages::shares.unlock', ['gallery' => $galleryB])
        ->set('password', 'secretB')
        ->call('unlock');

    get('shares/AAA111/'.$galleryA->slug)->assertStatus(200);
    get('shares/BBB222/'.$galleryB->slug)->assertStatus(200);
});
