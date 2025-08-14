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

test('users can see the branding page', function () {
    $response = actingAs($this->user)->get('/branding');

    $response->assertStatus(200);
});

test('guests cannot view the branding page', function () {
    $response = get('/branding');

    $response->assertRedirect('/login');
});

test('a brand logo can be saved', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding')
        ->set('form.logo', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_path)->not->toBeNull();
    expect(Team::first()->brand_logo_url)->not->toBeNull();
});

test('a brand logo icon can be saved', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding')
        ->set('form.logoIcon', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_icon_path)->not->toBeNull();
    expect(Team::first()->brand_logo_icon_url)->not->toBeNull();
});

test('a brand watermark can be saved', function () {
    Storage::fake('s3');
    expect($this->team->brand_watermark_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding')
        ->set('form.watermark', UploadedFile::fake()->image('watermark.png'))
        ->call('save');

    expect(Team::first()->brand_watermark_path)->not->toBeNull();
    expect(Team::first()->brand_watermark_url)->not->toBeNull();
});

test('watermark position can be changed', function () {
    expect($this->team->brand_watermark_position)->not()->toBe('bottom');

    Volt::actingAs($this->user)->test('pages.branding')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

test('brand color can be changed', function () {
    expect($this->team->brand_color)->not()->toBe('red');

    Volt::actingAs($this->user)->test('pages.branding')
        ->set('form.color', 'red')
        ->call('save');

    expect($this->team->fresh()->brand_color)->toBe('red');
});

test('brand font can be changed', function () {
    expect($this->team->brand_font)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding')
        ->set('form.font', 'Montserrat')
        ->call('save');

    expect($this->team->fresh()->brand_font)->toBe('Montserrat');
});
