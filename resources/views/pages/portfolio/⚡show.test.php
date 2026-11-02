<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('excludes non-image photos from the portfolio grid', function () {
    $gallery = Gallery::factory()->public()->for($this->team)->create();
    $image = Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg']);
    $pdf = Photo::factory()->pdf()->for($gallery)->create(['name' => 'composite.pdf']);

    $component = Livewire::test('pages::portfolio.show', ['gallery' => $gallery]);

    expect($component->photos->contains($image))->toBeTrue();
    expect($component->photos->contains($pdf))->toBeFalse();
});

it('does not overflow on mobile and fills the square grid cells', function () {
    $gallery = Gallery::factory()->public()->for($this->team)->create();
    Photo::factory()->count(3)->for($gallery)->create(['status' => 'processed']);

    $page = visit("/@{$this->team->handle}/portfolio/{$gallery->ulid}");

    foreach ([320, 375] as $width) {
        $page->resize($width, 900);
        $page->wait(0.1);

        expect($page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))
            ->toBeTrue();
    }

    expect($page->script("(() => { const cell = document.querySelector('.aspect-square'); const image = cell.querySelector('img'); return Math.abs(cell.clientWidth - image.clientWidth) < 2 && Math.abs(cell.clientHeight - image.clientHeight) < 2; })()"))
        ->toBeTrue();
});

it('limits the portfolio grid and loads more photos on demand', function () {
    $gallery = Gallery::factory()->public()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $component = Livewire::test('pages::portfolio.show', ['gallery' => $gallery]);

    expect($component->photos)->toHaveCount(60)
        ->and($component->photoCount)->toBe(65)
        ->and($component->html())->toContain('x-on:scroll.window.throttle.150ms');

    $component->call('loadMore');

    expect($component->photos)->toHaveCount(65);
});
