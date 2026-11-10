<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

/*
 * The Closeup Panel is a photographer-only tool. Clients never see the trigger,
 * the panel or any of the scoring data, so there is no gallery flag to gate it
 * and nothing client-facing to configure.
 */

it('always renders the closeup panel on the owner viewer', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    actingAs($this->user)
        ->get(route('galleries.photos.show', ['gallery' => $gallery, 'photo' => $photo]))
        ->assertOk()
        ->assertSeeHtml('data-closeup-panel')
        ->assertSeeHtml('data-closeup-open')
        ->assertSeeHtml('x-on:closeup-open.window="show()"')
        ->assertSeeHtml('x-on:closeup-close.window="close()"')
        ->assertSeeHtml('x-on:closeup-opened.window="active = true"')
        ->assertSeeHtml('x-on:closeup-closed.window="active = false"')
        ->assertSeeHtml('aria-pressed="true"')
        ->assertSeeHtml('aria-pressed="false"')
        ->assertSeeHtml('data-closeup-close');
});

it('never renders the closeup panel or its trigger on the shared viewer', function () {
    $gallery = Gallery::factory()->for($this->team)->shared()->create();
    $photo = Photo::factory()->for($gallery)->create();

    $this->get(route('shares.photos.show', ['gallery' => $gallery, 'photo' => $photo]))
        ->assertOk()
        ->assertDontSeeHtml('data-closeup-panel')
        ->assertDontSeeHtml('data-closeup-open')
        ->assertDontSee('Alpine.data(\'closeupPanel\'');
});

it('has no closeup option in the share form or the gallery model', function () {
    $gallery = Gallery::factory()->for($this->team)->shared()->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->assertDontSee('Enable Closeup Panel')
        ->assertDontSee('share-closeup-toggle');

    expect($component->instance()->shareForm)->not->toHaveProperty('closeupEnabled')
        ->and($gallery->getFillable())->not->toContain('is_closeup_enabled')
        ->and(array_keys($this->user->shareGalleryDefaults()))->not->toContain('closeup_enabled');
});

it('feeds the panel the untouched original url for sharpness and zoom', function () {
    // Pin the CDN so the assertions are deterministic: the local .env may use
    // Bunny (`width=`) while CI uses wsrv.nl (`w=`).
    config(['picstome.photo_cdn_domain' => 'bunny']);

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'teams/1/photos/native.jpg',
        'disk' => 's3',
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo]);

    // The CDN preview carries resize params; the original must not, otherwise
    // sharpness would be measured on pixels the CDN invented. Check for the
    // query string itself instead of one vendor's parameter names.
    expect($photo->large_thumbnail_url)->toContain('?')
        ->and($photo->large_thumbnail_url)->toContain('width=')
        ->and($photo->original_url)->not->toContain('?')
        ->and($photo->original_url)->not->toContain('width=')
        ->and($photo->original_url)->not->toContain('format=')
        ->and($photo->original_url)->toContain('native.jpg');

    expect($component->html())->toContain('originalUrl:');
});
