<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('allows users to see the logos branding page', function () {
    $response = actingAs($this->user)->get('/branding/logos');

    $response->assertStatus(200);
});

it('shows guidance about where each logo is used', function () {
    actingAs($this->user)->get('/branding/logos')
        ->assertSee('Used as the favicon')
        ->assertSee('Displayed on your public portfolio')
        ->assertSee('brandingImageUpload', false);
});

it('keeps the save button disabled state in sync with uploads', function () {
    actingAs($this->user)->get('/branding/logos')
        ->assertSee('x-bind:disabled="uploads > 0', false)
        ->assertDontSee('wire:loading.attr="disabled"', false);
});

it('does not save when no image is uploaded', function () {
    Livewire::actingAs($this->user)->test('pages::branding.logos')
        ->call('save')
        ->assertNotDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_logo_path)->toBeNull();
    expect($this->team->fresh()->brand_logo_icon_path)->toBeNull();
});

it('can save a brand logo', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.logos')
        ->set('form.logo', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_path)->not->toBeNull();
    expect(Team::first()->brand_logo_url)->not->toBeNull();
});

it('can delete the brand logo', function () {
    Storage::fake('s3');

    $this->team->updateBrandLogo(UploadedFile::fake()->image('logo.png'));
    $path = $this->team->fresh()->brand_logo_path;

    Livewire::actingAs($this->user)->test('pages::branding.logos')
        ->call('deleteLogo')
        ->assertDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_logo_path)->toBeNull();
    Storage::disk('s3')->assertMissing($path);
});

it('can delete the brand logo icon', function () {
    Storage::fake('s3');

    $this->team->updateBrandLogoIcon(UploadedFile::fake()->image('icon.png'));
    $path = $this->team->fresh()->brand_logo_icon_path;

    Livewire::actingAs($this->user)->test('pages::branding.logos')
        ->call('deleteLogoIcon')
        ->assertDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_logo_icon_path)->toBeNull();
    Storage::disk('s3')->assertMissing($path);
});

it('can save a brand logo icon', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.logos')
        ->set('form.logoIcon', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_icon_path)->not->toBeNull();
    expect(Team::first()->brand_logo_icon_url)->not->toBeNull();
});
