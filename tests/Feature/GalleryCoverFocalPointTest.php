<?php

use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Photoshoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

/**
 * Create a gallery whose cover photo has a focal point set by the
 * photographer, mirroring what the cover focus overlay persists.
 */
function galleryWithFocalPoint(float $x, float $y, string $state = 'shared'): Gallery
{
    $gallery = Gallery::factory()->{$state}()->for(test()->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);
    $gallery->setCoverPhoto($photo);
    $gallery->setCoverFocalPoint($x, $y);

    return $gallery->fresh(['coverPhoto', 'firstImagePhoto']);
}

it('exposes the cover focal point as a css object position', function () {
    $gallery = galleryWithFocalPoint(0.25, 0.75);

    expect($gallery->cover_focal_position)->toBe('25% 75%');
});

it('clamps the cover focal point inside the image bounds', function () {
    $gallery = galleryWithFocalPoint(1.4, -0.2);

    expect($gallery->cover_focal_position)->toBe('100% 0%');
});

it('returns no focal position when the gallery has no explicit cover', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();

    expect($gallery->cover_focal_position)->toBeNull();
});

it('applies the focal point to the gallery card thumbnail', function () {
    galleryWithFocalPoint(0.25, 0.75);

    Livewire::actingAs($this->user)
        ->test('pages::galleries')
        ->assertSeeHtml('object-position: 25% 75%');
});

it('applies the focal point to the public portfolio card thumbnail', function () {
    galleryWithFocalPoint(0.25, 0.75, 'public');

    get(route('portfolio.index', ['handle' => $this->team->handle]))
        ->assertOk()
        ->assertSee('object-position: 25% 75%', false);
});

it('applies the focal point to the portfolio gallery hero', function () {
    $gallery = galleryWithFocalPoint(0.25, 0.75, 'public');
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::test('pages::portfolio.show', ['gallery' => $gallery])
        ->assertSeeHtml('object-position: 25% 75%');
});

it('applies the focal point to client gallery card thumbnails', function () {
    $customer = Customer::factory()->create(['team_id' => $this->team->id]);
    $photoshoot = Photoshoot::factory()->for($customer)->for($this->team)->create();

    $gallery = Gallery::factory()->shared()->for($photoshoot)->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);
    $gallery->setCoverPhoto($photo);
    $gallery->setCoverFocalPoint(0.25, 0.75);

    get('/clients/'.$customer->ulid)
        ->assertOk()
        ->assertSee('object-position: 25% 75%', false);
});

it('applies the focal point to the shared gallery hero', function () {
    $gallery = galleryWithFocalPoint(0.25, 0.75);

    Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])
        ->assertSeeHtml('object-position: 25% 75%');
});

it('applies the focal point to the portfolio manager thumbnails', function () {
    galleryWithFocalPoint(0.25, 0.75, 'public');

    Livewire::actingAs($this->user)
        ->test('pages::portfolio')
        ->assertSeeHtml('object-position: 25% 75%');
});

it('keeps the default centred crop when a gallery has no cover photo', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::actingAs($this->user)
        ->test('pages::galleries')
        ->assertDontSeeHtml('object-position:');
});
