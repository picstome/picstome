<?php

use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team galleries galleries', function () {
    $galleryA = Gallery::factory()->for($this->team)->create();
    $galleryB = Gallery::factory()->for(Team::factory())->create();
    $galleryC = Gallery::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/galleries');
    $component = Livewire::test('pages::galleries');

    $response->assertStatus(200);
    expect($component->galleries->count())->toBe(2);
    expect($component->galleries->contains($galleryA))->toBeTrue();
    expect($component->galleries->contains($galleryB))->toBeFalse();
    expect($component->galleries->contains($galleryC))->toBeTrue();
});

test('can create a team gallery', function () {
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'Just a Photo Gallery')
        ->call('save');

    $component->assertRedirect('/galleries/1');
    expect($this->team->galleries()->count())->toBe(1);
    expect($this->team->galleries()->first()->name)->toBe('Just a Photo Gallery');
    expect($this->team->galleries()->first()->keep_original_size)->toBeTrue();
});

test('can create a team gallery that does not keep photos at their original size', function () {
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'Just a Photo Gallery')
        ->set('form.keepOriginalSize', false)
        ->call('save');

    expect(Gallery::count())->toBe(1);
    expect(Gallery::first()->name)->toBe('Just a Photo Gallery');
    expect(Gallery::first()->keep_original_size)->toBeFalse();
});

test('remembers the keep original size option for new galleries', function () {
    Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'First Gallery')
        ->set('form.keepOriginalSize', true)
        ->call('save');

    $component = Livewire::actingAs($this->user)->test('pages::galleries');

    expect($component->get('form.keepOriginalSize'))->toBeTrue();
});

test('can create a team gallery with no expiration date when subscribed', function () {
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'No Expiration Gallery')
        ->set('form.expirationDate', '')
        ->call('save');

    $gallery = $this->team->galleries()->first();

    expect($gallery)->not->toBeNull();
    expect($gallery->name)->toBe('No Expiration Gallery');
    expect($gallery->expiration_date)->toBeNull();
});

test('cannot create a team gallery with no expiration date when not subscribed', function () {
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'No Expiration Gallery')
        ->set('form.expirationDate', '')
        ->call('save');

    $component->assertHasErrors(['form.expirationDate' => 'required']);
    expect($this->team->galleries()->count())->toBe(0);
});

test('can create a team gallery with an expiration date', function () {
    $expiration = now()->addDays(7)->toDateString();

    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'Expiring Gallery')
        ->set('form.expirationDate', $expiration)
        ->call('save');

    $gallery = $this->team->galleries()->first();

    expect($gallery)->not->toBeNull();
    expect($gallery->name)->toBe('Expiring Gallery');
    expect($gallery->expiration_date->toDateString())->toBe($expiration);
});

test('cannot create a gallery with an invalid expiration date', function () {
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'Invalid Expiration Gallery')
        ->set('form.expirationDate', 'not-a-date')
        ->call('save');

    $component->assertHasErrors(['form.expirationDate' => 'date']);
    expect($this->team->galleries()->count())->toBe(0);

    $pastDate = now()->subDay()->toDateString();
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'Past Expiration Gallery')
        ->set('form.expirationDate', $pastDate)
        ->call('save');

    $component->assertHasErrors(['form.expirationDate' => 'after_or_equal']);
    expect($this->team->galleries()->count())->toBe(0);

    $futureDateOverOneYear = now()->addYear()->addDay()->toDateString();
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->set('form.name', 'Too Far Future Gallery')
        ->set('form.expirationDate', $futureDateOverOneYear)
        ->call('save');

    $component->assertHasErrors(['form.expirationDate' => 'before_or_equal']);
    expect($this->team->galleries()->count())->toBe(0);
});

test('subscribed users can start a live shoot', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->call('startLiveShoot');

    $component->assertRedirect('/galleries/1?live=1');

    $gallery = $this->team->galleries()->first();
    expect($gallery->name)->toBe('Shoot '.now()->format('Y-m-d'));

    $account = $this->team->ftpAccount;
    expect($account)->not->toBeNull();
    expect($account->routesTo($gallery))->toBeTrue();
    expect($account->password)->toMatch('/^\d{8}$/');
});

test('live shoot galleries keep the original size preference', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    $this->user->rememberGalleryDefaults(['keep_original_size' => true]);

    Livewire::actingAs($this->user)->test('pages::galleries')->call('startLiveShoot');

    expect($this->team->galleries()->first()->keep_original_size)->toBeTrue();
});

test('camera galleries keep the original size by default', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    Livewire::actingAs($this->user)->test('pages::galleries')->call('startLiveShoot');

    expect($this->team->galleries()->first()->keep_original_size)->toBeTrue();
});

test('live shoot galleries follow the last chosen original size preference', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    $this->user->rememberGalleryDefaults(['keep_original_size' => false]);

    Livewire::actingAs($this->user)->test('pages::galleries')->call('startLiveShoot');

    expect($this->team->galleries()->first()->keep_original_size)->toBeFalse();
});

test('live shoot gallery names increment within the same day', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    Livewire::actingAs($this->user)->test('pages::galleries')->call('startLiveShoot');
    Livewire::actingAs($this->user)->test('pages::galleries')->call('startLiveShoot');

    $names = $this->team->galleries()->pluck('name');

    expect($names)->toContain('Shoot '.now()->format('Y-m-d'));
    expect($names)->toContain('Shoot '.now()->format('Y-m-d').' (2)');

    // One stable team account, routing to the latest shoot.
    expect(FtpAccount::count())->toBe(1);
    expect($this->team->ftpAccount->activeGallery->name)->toBe('Shoot '.now()->format('Y-m-d').' (2)');
});

test('starting a live shoot requires a subscription', function () {
    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->call('startLiveShoot');

    expect($this->team->galleries()->count())->toBe(0);
    expect(FtpAccount::count())->toBe(0);
});

test('starting a live shoot keeps the gallery when the upload server is unreachable', function () {
    config([
        'picstome.ftp_uploads.sftpgo_url' => 'https://sftpgo.test',
        'picstome.ftp_uploads.sftpgo_api_key' => 'secret-key',
    ]);

    Http::fake([
        'sftpgo.test/*' => Http::response('boom', 500),
    ]);

    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

    Livewire::actingAs($this->user)->test('pages::galleries')->call('startLiveShoot');

    expect($this->team->galleries()->count())->toBe(1);
    expect(FtpAccount::count())->toBe(0);
});

test('shows a wider pagination window with first and last controls', function () {
    Gallery::factory()->for($this->team)->count(150)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries')
        ->call('gotoPage', 4);

    $component
        ->assertSeeHtml('data-pagination-first')
        ->assertSeeHtml('data-pagination-last')
        ->assertSeeHtml('data-pagination-page="2"')
        ->assertSeeHtml('data-pagination-page="6"');
});

test('scrolls to the top after changing gallery pages', function () {
    Gallery::factory()->for($this->team)->count(30)->create();

    $this->actingAs($this->user);

    $page = visit('/galleries');
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(0.2);

    expect($page->script('window.scrollY'))->toBeGreaterThan(0);

    $page->click('[data-pagination-page="2"]');
    $page->wait(1);

    expect($page->script('window.scrollY'))->toBeLessThan(2);
});
