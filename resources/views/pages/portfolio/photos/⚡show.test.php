<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('renders an inert document card for pdf photos', function () {
    $gallery = Gallery::factory()->public()->for($this->team)->create();
    $photo = Photo::factory()->pdf()->for($gallery)->create(['name' => 'composite.pdf']);

    $response = get(route('portfolio.photos.show', ['handle' => $this->team->handle, 'gallery' => $gallery, 'photo' => $photo]));

    $response->assertOk();
    $response->assertSee('composite.pdf');
});

it('skips non-image photos in portfolio navigation', function () {
    $gallery = Gallery::factory()->public()->for($this->team)->create();
    $pdf = Photo::factory()->pdf()->for($gallery)->create(['name' => 'a.pdf']);
    $imageB = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);
    $imageC = Photo::factory()->for($gallery)->create(['name' => 'c.jpg']);

    $component = Livewire::test('pages::portfolio.photos.show', ['photo' => $imageB]);

    expect($component->previous)->toBeNull();
    expect($component->next->id)->toBe($imageC->id);

    $component = Livewire::test('pages::portfolio.photos.show', ['photo' => $pdf]);

    expect($component->previous)->toBeNull();
    expect($component->next)->toBeNull();
});
