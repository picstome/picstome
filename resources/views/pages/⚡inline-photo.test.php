<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('can open a pdf inline', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/document.pdf', '%PDF-1.4 fake pdf body');

    Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create([
        'disk' => 's3',
        'path' => 'photos/document.pdf',
    ]);

    $response = actingAs($this->user)->get('galleries/1/photos/1/pdf');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'inline; filename=document.pdf');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('guests cannot open any galleries pdfs', function () {
    Photo::factory()->pdf()->create();

    $response = get('/galleries/1/photos/1/pdf');

    $response->assertRedirect('/login');
});

test('users cannot open the team gallery pdfs of other users', function () {
    Photo::factory()->pdf()->for(Gallery::factory()->for(Team::factory()))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1/pdf');

    $response->assertStatus(403);
});

test('non-pdf photos cannot be opened inline', function () {
    Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1/pdf');

    $response->assertStatus(404);
});
