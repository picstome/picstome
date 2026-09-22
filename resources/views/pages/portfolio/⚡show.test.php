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
