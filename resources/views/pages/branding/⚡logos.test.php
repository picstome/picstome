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

it('allows users to see the logos branding page', function () {
    $response = actingAs($this->user)->get('/branding/logos');

    $response->assertStatus(200);
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

it('can save a brand logo icon', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.logos')
        ->set('form.logoIcon', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_icon_path)->not->toBeNull();
    expect(Team::first()->brand_logo_icon_url)->not->toBeNull();
});
