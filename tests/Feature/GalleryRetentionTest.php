<?php

use App\Models\Gallery;
use App\Models\User;
use App\Notifications\GalleryExpirationReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('caps a free gallery at one year from its creation', function () {
    $gallery = Gallery::factory()->for($this->team)->create([
        'created_at' => now()->subMonths(10),
        'expiration_date' => now()->addMonth(),
    ]);

    // Ten months old, so only two months remain inside the cap.
    $beyondCap = now()->addMonths(4)->toDateString();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('form.expirationDate', $beyondCap)
        ->call('update')
        ->assertHasErrors(['form.expirationDate' => 'before_or_equal']);

    expect($gallery->fresh()->expiration_date->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('lets a free team shorten the expiration and move it inside the cap', function () {
    $gallery = Gallery::factory()->for($this->team)->create([
        'created_at' => now()->subMonths(6),
        'expiration_date' => now()->addMonth(),
    ]);

    $insideCap = now()->addMonths(4)->toDateString();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('form.expirationDate', $insideCap)
        ->call('update')
        ->assertHasNoErrors();

    expect($gallery->fresh()->expiration_date->toDateString())->toBe($insideCap);
});

it('does not let a lifetime team clear the expiration date', function () {
    $this->team->update(['lifetime_at' => now()]);

    $gallery = Gallery::factory()->for($this->team)->create([
        'expiration_date' => now()->addMonth(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('form.expirationDate', '')
        ->call('update')
        ->assertHasErrors(['form.expirationDate' => 'required']);

    expect($gallery->fresh()->expiration_date)->not->toBeNull();
});

it('lets a subscribed team clear the expiration date', function () {
    Subscription::factory()->for($this->team, 'owner')->create();

    $gallery = Gallery::factory()->for($this->team)->create([
        'expiration_date' => now()->addMonth(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('form.expirationDate', '')
        ->call('update')
        ->assertHasNoErrors();

    expect($gallery->fresh()->expiration_date)->toBeNull();
});

it('does not cap a subscribed team at the creation date', function () {
    Subscription::factory()->for($this->team, 'owner')->create();

    $gallery = Gallery::factory()->for($this->team)->create([
        'created_at' => now()->subYears(2),
        'expiration_date' => now()->addMonth(),
    ]);

    // The creation date is already more than a year in the past, but the
    // subscription lifts that ceiling: only the rolling one-year window
    // applied to every plan remains.
    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('form.expirationDate', now()->addMonths(10)->toDateString())
        ->call('update')
        ->assertHasNoErrors();

    expect($gallery->fresh()->expiration_date->toDateString())
        ->toBe(now()->addMonths(10)->toDateString());
});

it('keeps an over-cap date instead of forcing a destructive edit', function () {
    // Gallery created two years ago with a date beyond the cap.
    $gallery = Gallery::factory()->for($this->team)->create([
        'created_at' => now()->subYears(2),
        'expiration_date' => now()->addMonths(3),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('form.name', 'Renamed')
        ->call('update')
        ->assertHasNoErrors();

    expect($gallery->fresh()->expiration_date->toDateString())
        ->toBe(now()->addMonths(3)->toDateString());
});

it('clamps the date when a public gallery becomes private', function () {
    $gallery = Gallery::factory()->for($this->team)->public()->create([
        'created_at' => now()->subMonths(11),
        'expiration_date' => null,
    ]);

    $gallery->makePrivate();

    // One month from now would pass the one-year cap, so it stops at the cap.
    expect($gallery->fresh()->expiration_date->toDateString())
        ->toBe($gallery->created_at->copy()->addYear()->toDateString());
});

it('re-arms the expiration reminder when the date changes', function () {
    $gallery = Gallery::factory()->for($this->team)->create([
        'expiration_date' => now()->addDays(2),
        'reminder_sent_at' => now()->subDay(),
    ]);

    $gallery->update(['expiration_date' => now()->addDays(20)]);

    expect($gallery->fresh()->reminder_sent_at)->toBeNull();
});

it('gives a date to an old gallery that had none', function () {
    $gallery = Gallery::factory()->for($this->team)->create([
        'created_at' => now()->subYears(2),
        'expiration_date' => null,
    ]);

    // Grace period: nothing is deleted overnight.
    expect($gallery->retentionCap()->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('sends the expiration reminder to lifetime teams', function () {
    Notification::fake();

    $this->team->update(['lifetime_at' => now()]);

    $gallery = Gallery::factory()->for($this->team)->create([
        'expiration_date' => now()->addDays(2),
    ]);

    artisan('galleries:send-expiration-reminders')->assertExitCode(0);

    Notification::assertSentTo($this->team->owner, GalleryExpirationReminder::class);
});
