<?php

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = \App\Models\User::factory()->withPersonalTeam()->create();
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

it('can change watermark position', function () {
    expect($this->team->brand_watermark_position)->not()->toBe('bottom');

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

it('can change watermark transparency', function () {
    expect($this->team->brand_watermark_transparency)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkTransparency', 50)
        ->call('save');

    expect($this->team->fresh()->brand_watermark_transparency)->toBe(50);
});
