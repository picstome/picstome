<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('pdf photos render a static document tile with the file name', function () {
    $photo = Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create();

    $html = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo])->html();

    expect($html)->toContain('document.pdf');
    expect($html)->toContain('<svg');
    expect($html)->not->toContain('<video');
    expect($html)->not->toContain('animate-pulse');
});

test('pdf photos have no set as cover action', function () {
    $photo = Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create();

    $html = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo])->html();

    expect($html)->not->toContain('Set as Cover');
    expect($html)->not->toContain('Remove as Cover');
    expect($html)->toContain('Delete');
});
