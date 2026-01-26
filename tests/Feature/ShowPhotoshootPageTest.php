<?php

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team photoshoot', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/photoshoots/1');

    $response->assertStatus(200);
});

test('guests cannot view any photoshoot', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $response = get('/photoshoots/1');

    $response->assertRedirect('/login');
});

test('users cannot view the team photoshoot of others', function () {
    $photoshoot = Photoshoot::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/photoshoots/1');

    $response->assertStatus(403);
});

test('can add a gallery', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('galleryForm.name', 'Gallery for a photoshoot')
        ->call('addGallery');

    expect($photoshoot->galleries()->count())->toBe(1);
    $gallery = $photoshoot->galleries()->first();
    expect($gallery->name)->toBe('Gallery for a photoshoot');
    expect($gallery->expiration_date)->not->toBeNull();
    expect($gallery->expiration_date->format('Y-m-d'))->toBe(now()->addMonth()->format('Y-m-d'));
});

test('can add a gallery with custom expiration date', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();
    $expiration = now()->addDays(7)->toDateString();

    Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('galleryForm.name', 'Gallery with custom expiration')
        ->set('galleryForm.expirationDate', $expiration)
        ->call('addGallery');

    $gallery = $photoshoot->galleries()->first();
    expect($gallery->name)->toBe('Gallery with custom expiration');
    expect($gallery->expiration_date->toDateString())->toBe($expiration);
});

test('can add a gallery with no expiration date when subscribed', function () {
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('galleryForm.name', 'Gallery with no expiration')
        ->set('galleryForm.expirationDate', '')
        ->call('addGallery');

    $gallery = $photoshoot->galleries()->first();
    expect($gallery->name)->toBe('Gallery with no expiration');
    expect($gallery->expiration_date)->toBeNull();
});

test('cannot add a gallery with no expiration date when not subscribed', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('galleryForm.name', 'Gallery with no expiration')
        ->set('galleryForm.expirationDate', '')
        ->call('addGallery');

    $component->assertHasErrors(['galleryForm.expirationDate' => 'required']);
    expect($photoshoot->galleries()->count())->toBe(0);
});

test('cannot add a gallery with invalid expiration date', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('galleryForm.name', 'Gallery with invalid date')
        ->set('galleryForm.expirationDate', 'not-a-date')
        ->call('addGallery');

    $component->assertHasErrors(['galleryForm.expirationDate' => 'date']);
    expect($photoshoot->galleries()->count())->toBe(0);

    $pastDate = now()->subDay()->toDateString();
    $component = Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('galleryForm.name', 'Gallery with past date')
        ->set('galleryForm.expirationDate', $pastDate)
        ->call('addGallery');

    $component->assertHasErrors(['galleryForm.expirationDate' => 'after_or_equal']);
    expect($photoshoot->galleries()->count())->toBe(0);
});

test('can delete team photoshoot', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->has(
        Gallery::factory()->has(
            Photo::factory()->count(2)
        )->count(2)
    )->create();
    expect(Gallery::count())->toBe(2);
    expect(Photo::count())->toBe(4);

    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->call('delete');

    $component->assertRedirect('/photoshoots');
    expect(Photoshoot::count())->toBe(0);
    expect(Gallery::count())->toBe(0);
    expect(Photo::count())->toBe(0);
});

test('can delete team photoshoot preserving galleries', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->has(
        Gallery::factory()->has(
            Photo::factory()->count(2)
        )->count(2)
    )->create();
    expect(Gallery::count())->toBe(2);
    expect(Photo::count())->toBe(4);

    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->call('deletePreservingGalleries');

    $component->assertRedirect('/photoshoots');
    expect(Photoshoot::count())->toBe(0);
    expect(Gallery::count())->toBe(2);
    expect(Photo::count())->toBe(4);
});

test('can edit a team photoshoot', function () {
    $customer = Customer::factory()->for($this->team)->create();
    $photoshoot = Photoshoot::factory()->for($this->team)->create(['customer_id' => $customer->id]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('form.name', 'Edited photoshoot')
        ->set('form.date', Carbon::parse('2025-12-12'))
        ->set('form.price', '400')
        ->set('form.location', 'Fake City')
        ->set('form.comment', 'Edited comment')
        ->call('update');

    tap($photoshoot->fresh(), function (Photoshoot $photoshoot) {
        expect($photoshoot->name)->toBe('Edited photoshoot');
        expect((string) $photoshoot->date)->toBe('2025-12-12 00:00:00');
        expect($photoshoot->price)->toBe(400);
        expect($photoshoot->location)->toBe('Fake City');
        expect($photoshoot->comment)->toBe('Edited comment');
    });
});

test('can add new contract', function () {
    $photoshoot = Photoshoot::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('contractForm.title', 'A contract title')
        ->set('contractForm.description', 'A contract description')
        ->set('contractForm.location', 'A location')
        ->set('contractForm.shootingDate', Carbon::parse('12-12-2025'))
        ->set('contractForm.body', '<h3>Body in HTML</h3>')
        ->set('contractForm.signature_quantity', 3)
        ->call('addContract');

    $component->assertRedirect('/contracts/1');
    expect(Contract::count())->toBe(1);
    tap(Contract::first(), function (Contract $contract) {
        expect($contract->title)->toBe('A contract title');
        expect($contract->description)->toBe('A contract description');
        expect($contract->location)->toBe('A location');
        expect((string) $contract->shooting_date)->toBe('2025-12-12 00:00:00');
        expect($contract->markdown_body)->toBe('### Body in HTML');
        expect($contract->signaturesRemaining())->toBe(3);
        $contract->signatures()->each(function ($contract) {
            expect($contract->isSigned())->toBeFalse();
        });
    });
});
