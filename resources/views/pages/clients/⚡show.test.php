<?php

use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Photoshoot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->team = $this->customer->team;
    $this->photoshoot = Photoshoot::factory()->for($this->customer)->for($this->team)->create();
});

test('lists shared photoshoot galleries with links to their share pages', function () {
    $gallery = Gallery::factory()->shared()->for($this->photoshoot)->for($this->team)->create();

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertSee($gallery->name)
        ->assertSee(route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]));
});

test('hides unshared galleries', function () {
    $gallery = Gallery::factory()->unshared()->for($this->photoshoot)->for($this->team)->create();

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertDontSee($gallery->name);
});

test('omits standalone galleries even when shared', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertDontSee($gallery->name);
});

test('omits other customers galleries in the same team and other teams galleries', function () {
    $otherCustomer = Customer::factory()->for($this->team)->create();
    $otherPhotoshoot = Photoshoot::factory()->for($otherCustomer)->for($this->team)->create();
    Gallery::factory()->shared()->for($otherPhotoshoot)->for($this->team)->create(['name' => 'Same Team Other Customer']);
    Gallery::factory()->shared()->create(['name' => 'Other Team Gallery']);

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertDontSee('Same Team Other Customer')
        ->assertDontSee('Other Team Gallery');
});

test('protected gallery details stay hidden until unlocked', function () {
    Gallery::factory()->shared()->protected()->for($this->photoshoot)->for($this->team)->create(['name' => 'Locked Gallery']);

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertDontSee('Locked Gallery')
        ->assertSee(__('Protected galleries'));
});

test('hides the unlock form when no shared gallery is protected', function () {
    Gallery::factory()->shared()->for($this->photoshoot)->for($this->team)->create(['name' => 'Open Gallery']);

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertSee('Open Gallery')
        ->assertDontSee(__('Protected galleries'));
});

test('stores an empty-string password as no password', function () {
    $gallery = Gallery::factory()->shared()->for($this->photoshoot)->for($this->team)->create([
        'name' => 'Legacy Empty Password Gallery',
        'share_password' => '',
    ]);

    expect($gallery->fresh()->share_password)->toBeNull();

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertSee('Legacy Empty Password Gallery')
        ->assertDontSee(__('Protected galleries'));
});

test('one unlock on the index reveals the gallery and a same-password sibling', function () {
    Gallery::factory()->shared()->protected(password: 'secret')->for($this->photoshoot)->for($this->team)->create(['name' => 'Gallery A']);
    Gallery::factory()->shared()->protected(password: 'secret')->for($this->photoshoot)->for($this->team)->create(['name' => 'Gallery B']);

    Livewire::test('pages::clients.show', ['customer' => $this->customer])
        ->set('password', 'secret')
        ->call('unlock')
        ->assertHasNoErrors();

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertSee('Gallery A')
        ->assertSee('Gallery B');
});

test('wrong password shows a validation error and keeps galleries hidden', function () {
    Gallery::factory()->shared()->protected(password: 'secret')->for($this->photoshoot)->for($this->team)->create(['name' => 'Locked Gallery']);

    Livewire::test('pages::clients.show', ['customer' => $this->customer])
        ->set('password', 'wrong-password')
        ->call('unlock')
        ->assertHasErrors(['password' => trans('auth.failed')]);

    expect(session()->get('unlocked_gallery_ulids'))->toBeNull();

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertDontSee('Locked Gallery');
});

test('index unlock then direct share url visit is allowed', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->for($this->photoshoot)->for($this->team)->create();

    Livewire::test('pages::clients.show', ['customer' => $this->customer])
        ->set('password', 'secret')
        ->call('unlock');

    get('/shares/'.$gallery->ulid.'/'.$gallery->slug)->assertStatus(200);
});

test('unknown customer ulid returns 404', function () {
    get('/clients/nonexistent')->assertStatus(404);
});

test('renders an empty state when no galleries are shared', function () {
    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertSee(__('No galleries yet'));
});

test('unlocking a second password keeps the first galleries unlocked', function () {
    $galleryA = Gallery::factory()->shared()->protected(password: 'secretA')->for($this->photoshoot)->for($this->team)->create(['name' => 'Gallery A']);
    $galleryB = Gallery::factory()->shared()->protected(password: 'secretB')->for($this->photoshoot)->for($this->team)->create(['name' => 'Gallery B']);

    Livewire::test('pages::clients.show', ['customer' => $this->customer])
        ->set('password', 'secretA')
        ->call('unlock');

    Livewire::test('pages::clients.show', ['customer' => $this->customer])
        ->set('password', 'secretB')
        ->call('unlock');

    get('/clients/'.$this->customer->ulid)
        ->assertStatus(200)
        ->assertSee('Gallery A')
        ->assertSee('Gallery B');

    get('/shares/'.$galleryA->ulid.'/'.$galleryA->slug)->assertStatus(200);
    get('/shares/'.$galleryB->ulid.'/'.$galleryB->slug)->assertStatus(200);
});
