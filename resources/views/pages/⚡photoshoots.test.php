<?php

use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('shows only photoshoots belonging to users team', function () {
    $photoshootA = Photoshoot::factory()->for($this->team)->create();
    $photoshootB = Photoshoot::factory()->for(Team::factory())->create();
    $photoshootC = Photoshoot::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/photoshoots');
    $component = Livewire::test('pages::photoshoots');

    $response->assertStatus(200);
    expect($component->photoshoots->count())->toBe(2);
    expect($component->photoshoots->contains($photoshootA))->toBeTrue();
    expect($component->photoshoots->contains($photoshootB))->toBeFalse();
    expect($component->photoshoots->contains($photoshootC))->toBeTrue();
});

it('uses the first image of the latest gallery with photos as the cover', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create(['date' => now()]);

    $olderGallery = Gallery::factory()->for($this->team)->for($photoshoot)->create();
    $olderPhoto = Photo::factory()->for($olderGallery)->create([
        'name' => 'older.jpg',
        'path' => 'galleries/older.jpg',
    ]);

    $galleryWithImage = Gallery::factory()->for($this->team)->for($photoshoot)->create();
    $coverPhoto = Photo::factory()->for($galleryWithImage)->create([
        'name' => 'cover.jpg',
        'path' => 'galleries/cover.jpg',
    ]);

    $latestVideoGallery = Gallery::factory()->for($this->team)->for($photoshoot)->create();
    Photo::factory()->for($latestVideoGallery)->create([
        'name' => 'clip.mp4',
        'path' => 'galleries/clip.mp4',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots');

    $component
        ->assertSee($coverPhoto->small_thumbnail_url)
        ->assertDontSee($olderPhoto->small_thumbnail_url);
});

it('shows a placeholder when no gallery has a photo', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();
    $gallery = Gallery::factory()->for($this->team)->for($photoshoot)->create();

    Photo::factory()->for($gallery)->create([
        'name' => 'clip.mp4',
        'path' => 'galleries/clip.mp4',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots');

    $component->assertSeeHtml('data-test="photoshoot-cover-placeholder"');
});

it('paginates photoshoots', function () {
    Photoshoot::factory()->for($this->team)->count(25)->create();

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots');

    expect($component->photoshoots->total())->toBe(25);
    expect($component->photoshoots->hasPages())->toBeTrue();
    expect($component->photoshoots->count())->toBe(24);
});

it('allows a user to create a photoshoot with a customer email', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots')
        ->set('form.name', 'John\'s Photoshoot')
        ->set('form.customerName', 'John Doe')
        ->set('form.customerEmail', 'john@example.com')
        ->call('save');

    $customer = $this->team->photoshoots()->first()->customer;
    expect($customer->name)->toBe('John Doe');
    expect($customer->email)->toBe('john@example.com');
});

it('allows a user to create a photoshoot with an existing customer', function () {
    $customer = Customer::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots')
        ->set('form.name', 'Jane Photoshoot')
        ->set('form.customer', $customer->id)
        ->call('save');

    $photoshoot = $this->team->photoshoots()->first();
    expect($photoshoot->customer->is($customer))->toBeTrue();
});


it('shows a wider pagination window with first and last controls', function () {
    Photoshoot::factory()->for($this->team)->count(150)->create();

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots')
        ->call('gotoPage', 4);

    $component
        ->assertSeeHtml('data-pagination-first')
        ->assertSeeHtml('data-pagination-last')
        ->assertSeeHtml('data-pagination-page="2"')
        ->assertSeeHtml('data-pagination-page="6"');
});

it('scrolls to the top after changing photoshoot pages', function () {
    Photoshoot::factory()->for($this->team)->count(30)->create();

    $this->actingAs($this->user);

    $page = visit('/photoshoots');
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.2);

    expect($page->script('window.scrollY'))->toBeGreaterThan(0);

    $page->click('[data-pagination-page="2"]');
    $page->wait(1);

    expect($page->script('window.scrollY'))->toBeLessThan(2);
});
