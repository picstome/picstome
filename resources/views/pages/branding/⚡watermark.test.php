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

it('allows users to see the watermark branding page', function () {
    $response = actingAs($this->user)->get('/branding/watermark');

    $response->assertStatus(200);
});

it('can save a brand watermark', function () {
    Storage::fake('s3');
    expect($this->team->brand_watermark_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermark', UploadedFile::fake()->image('watermark.png'))
        ->call('save');

    expect($this->team->fresh()->brand_watermark_path)->not->toBeNull();
    expect(Team::first()->brand_watermark_url)->not->toBeNull();
});

it('keeps the save button disabled state in sync with uploads', function () {
    actingAs($this->user)->get('/branding/watermark')
        ->assertSee('x-bind:disabled="uploads > 0', false)
        ->assertDontSee('wire:loading.attr="disabled"', false);
});

it('keeps the existing watermark path available to the save button', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->assertSet('brandWatermarkPath', $this->team->fresh()->brand_watermark_path)
        ->call('deleteWatermark')
        ->assertSet('brandWatermarkPath', null);
});

it('does not save settings when there is no watermark', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->set('form.watermarkTransparency', 30)
        ->call('save')
        ->assertNotDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_watermark_position)->toBe('top');
    expect($this->team->fresh()->brand_watermark_transparency)->toBeNull();
});

it('can delete the brand watermark', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));
    $path = $this->team->fresh()->brand_watermark_path;

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->call('deleteWatermark')
        ->assertDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_watermark_path)->toBeNull();
    Storage::disk('s3')->assertMissing($path);
});

it('can save settings when a watermark already exists', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->set('form.watermarkTransparency', 25)
        ->call('save')
        ->assertDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
    expect($this->team->fresh()->brand_watermark_transparency)->toBe(25);
});

it('can change watermark position', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    expect($this->team->fresh()->brand_watermark_position)->not()->toBe('bottom');

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

it('can change watermark transparency', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    expect($this->team->fresh()->brand_watermark_transparency)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkTransparency', 50)
        ->call('save');

    expect($this->team->fresh()->brand_watermark_transparency)->toBe(50);
});
