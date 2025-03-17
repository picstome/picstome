<?php

use App\Models\Contract;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

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
    $response->assertViewHas('photoshoot');
    expect($response['photoshoot']->is($photoshoot))->toBeTrue();
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

    Volt::actingAs($this->user)->test('pages.photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('form.name', 'Gallery for a photoshoot')
        ->call('addGallery');

    expect($photoshoot->galleries()->count())->toBe(1);
});

test('can delete team photoshoot', function () {
    $photoshoot = Photoshoot::factory()->has(
        Gallery::factory()->has(
            Photo::factory()->count(2)
        )->count(2)
    )->create();
    expect(Gallery::count())->toBe(2);
    expect(Photo::count())->toBe(4);

    $component = Volt::test('pages.photoshoots.show', ['photoshoot' => $photoshoot])
        ->call('delete');

    $component->assertRedirect('/photoshoots');
    expect(Photoshoot::count())->toBe(0);
    expect(Gallery::count())->toBe(0);
    expect(Photo::count())->toBe(0);
});

test('can delete team photoshoot preserving galleries', function () {
    $photoshoot = Photoshoot::factory()->has(
        Gallery::factory()->has(
            Photo::factory()->count(2)
        )->count(2)
    )->create();
    expect(Gallery::count())->toBe(2);
    expect(Photo::count())->toBe(4);

    $component = Volt::test('pages.photoshoots.show', ['photoshoot' => $photoshoot])
        ->call('deletePreservingGalleries');

    $component->assertRedirect('/photoshoots');
    expect(Photoshoot::count())->toBe(0);
    expect(Gallery::count())->toBe(2);
    expect(Photo::count())->toBe(4);
});

test('can edit a team photoshoot', function () {
    $photoshoot = Photoshoot::factory()->create();

    $component = Volt::test('pages.photoshoots.show', ['photoshoot' => $photoshoot])
        ->set('form.name', 'Edited photoshoot')
        ->set('form.customerName', 'Edited customer name')
        ->set('form.date', Carbon::parse('2025-12-12'))
        ->set('form.price', '400')
        ->set('form.location', 'Fake City')
        ->set('form.comment', 'Edited comment')
        ->call('update');

    tap($photoshoot->fresh(), function (Photoshoot $photoshoot) {
        expect($photoshoot->name)->toBe('Edited photoshoot');
        expect($photoshoot->customer_name)->toBe('Edited customer name');
        expect((string) $photoshoot->date)->toBe('2025-12-12 00:00:00');
        expect($photoshoot->price)->toBe(400);
        expect($photoshoot->location)->toBe('Fake City');
        expect($photoshoot->comment)->toBe('Edited comment');
    });
});

test('can add new contract', function () {
    $photoshoot = Photoshoot::factory()->create();

    $component = Volt::actingAs($this->user)->test('pages.photoshoots.show', ['photoshoot' => $photoshoot])
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
