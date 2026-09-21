<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shared pdf photos render a static document tile without polling', function () {
    $photo = Photo::factory()->pdf()->for(Gallery::factory()->shared()->create())->create();

    $html = Livewire::test('shared-photo-item', ['photo' => $photo])->html();

    expect($html)->toContain('document.pdf');
    expect($html)->toContain('<svg');
    expect($html)->not->toContain('wire:poll');
    expect($html)->not->toContain('<video');
    expect($html)->not->toContain('animate-pulse');
});
