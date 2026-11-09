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

it('renders the public profile without hydrating the portfolio photos', function () {
    $gallery = Gallery::factory()->for($this->team)->public()->create();
    Photo::factory()->for($gallery)->count(3)->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get('/@'.$this->team->handle)->assertOk();

    // The profile only needs to know whether a portfolio exists, not the
    // photos inside it.
    expect($queries)->toBeLessThan(20);
});

it('shows the portfolio button only when a public gallery exists', function () {
    $this->get('/@'.$this->team->handle)->assertDontSee(__('View Portfolio'));

    Gallery::factory()->for($this->team)->public()->create();

    $this->get('/@'.$this->team->handle)->assertSee(__('View Portfolio'));
});

it('hides the portfolio button when the portfolio is disabled', function () {
    Gallery::factory()->for($this->team)->public()->create();

    $this->team->update(['portfolio_public_disabled' => true]);

    $this->get('/@'.$this->team->handle)->assertDontSee(__('View Portfolio'));
});

it('loads the gallery covers of the galleries index with their gallery relation', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $gallery->update(['cover_photo_id' => $photo->id]);

    $component = Livewire::actingAs($this->user)->test('pages::galleries');

    expect($component->instance()->galleries->first()->coverPhoto->relationLoaded('gallery'))->toBeTrue();
});
