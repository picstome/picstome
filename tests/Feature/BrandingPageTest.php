<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('redirects main branding page to general settings', function () {
    $response = actingAs($this->user)->get('/branding');

    $response->assertRedirect('/branding/general');
});

it('allows users to see the general branding page', function () {
    $response = actingAs($this->user)->get('/branding/general');

    $response->assertStatus(200);
});

it('allows users to see the logos branding page', function () {
    $response = actingAs($this->user)->get('/branding/logos');

    $response->assertStatus(200);
});

it('allows users to see the watermark branding page', function () {
    $response = actingAs($this->user)->get('/branding/watermark');

    $response->assertStatus(200);
});

it('allows users to see the styling branding page', function () {
    $response = actingAs($this->user)->get('/branding/styling');

    $response->assertStatus(200);
});

it('prevents guests from viewing branding pages', function () {
    $response = get('/branding/general');

    $response->assertRedirect('/login');
});

it('can save a brand logo', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.logos')
        ->set('form.logo', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_path)->not->toBeNull();
    expect(Team::first()->brand_logo_url)->not->toBeNull();
});

it('can save a brand logo icon', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.logos')
        ->set('form.logoIcon', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_icon_path)->not->toBeNull();
    expect(Team::first()->brand_logo_icon_url)->not->toBeNull();
});

it('can save a brand watermark', function () {
    Storage::fake('s3');
    expect($this->team->brand_watermark_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermark', UploadedFile::fake()->image('watermark.png'))
        ->call('save');

    expect(Team::first()->brand_watermark_path)->not->toBeNull();
    expect(Team::first()->brand_watermark_url)->not->toBeNull();
});

it('can change watermark position', function () {
    expect($this->team->brand_watermark_position)->not()->toBe('bottom');

    Volt::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

it('can change brand color', function () {
    expect($this->team->brand_color)->not()->toBe('red');

    Volt::actingAs($this->user)->test('pages.branding.styling')
        ->set('form.color', 'red')
        ->call('save');

    expect($this->team->fresh()->brand_color)->toBe('red');
});

it('can change brand font', function () {
    expect($this->team->brand_font)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.styling')
        ->set('form.font', 'Montserrat')
        ->call('save');

    expect($this->team->fresh()->brand_font)->toBe('Montserrat');
});

it('can change watermark transparency', function () {
    expect($this->team->brand_watermark_transparency)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermarkTransparency', 50)
        ->call('save');

    expect($this->team->fresh()->brand_watermark_transparency)->toBe(50);
});

it('allows user to update handle successfully', function () {
    $newHandle = 'newhandle';

    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', $newHandle)
        ->call('save');

    expect($this->team->fresh()->handle)->toBe($newHandle);
});

it('validates handle must be lowercase', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'MixedCaseHandle')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents duplicate handles with uniqueness validation', function () {
    Team::factory()->create(['handle' => 'existinghandle']);

    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'existinghandle')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents special characters in handles', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'invalid@handle!')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('enforces minimum length for handles', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'a')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('enforces maximum length for handles', function () {
    $longHandle = str_repeat('a', 100);

    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', $longHandle)
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents empty string handles', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', '')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});


