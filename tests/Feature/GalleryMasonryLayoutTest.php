<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('renders the owner gallery as a masonry wall', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->assertSeeHtml('data-photo-masonry')
        ->assertSeeHtml('columns-2 sm:columns-3 xl:columns-4')
        ->assertSeeHtml('break-inside-avoid');
});

it('renders the public shared gallery as a full-width masonry wall', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])
        ->assertSeeHtml('data-photo-masonry')
        ->assertSeeHtml('columns-1 sm:columns-2 md:columns-3 lg:columns-4 2xl:columns-5');
});

it('renders the public portfolio gallery as a masonry wall', function () {
    $gallery = Gallery::factory()->public()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $this->get(route('portfolio.show', ['handle' => $this->team->handle, 'gallery' => $gallery]))
        ->assertSuccessful()
        ->assertSee('data-photo-masonry', false)
        ->assertSee('columns-1 sm:columns-2 md:columns-3 lg:columns-4 2xl:columns-5', false);
});

it('renders photo thumbnails at their natural aspect ratio', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('data-photo-card')
        ->toContain('break-inside-avoid')
        ->toContain('h-auto w-full')
        ->not->toContain('aspect-square')
        ->not->toContain('object-cover');
});
