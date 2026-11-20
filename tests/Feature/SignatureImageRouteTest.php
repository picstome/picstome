<?php

use App\Models\Contract;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    $contract = Contract::factory()->for($this->team)->create();

    $this->signature = Signature::factory()->for($contract)->create([
        'signature_image_path' => 'signatures/signature-a.png',
    ]);

    Storage::disk('s3')->put('signatures/signature-a.png', 'png-bytes');
});

it('streams the signature image to the owning team', function () {
    $this->actingAs($this->user)
        ->get(route('signatures.image', $this->signature))
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff');
});

it('refuses a member of another team', function () {
    $other = User::factory()->withPersonalTeam()->create();

    $this->actingAs($other)
        ->get(route('signatures.image', $this->signature))
        ->assertForbidden();
});

it('sends guests to the login screen', function () {
    $this->get(route('signatures.image', $this->signature))
        ->assertRedirect('/login');
});

it('points the contract page at the private route', function () {
    expect($this->signature->signature_image_url)
        ->toBe(route('signatures.image', $this->signature));
});

it('hides the link when no signature was uploaded', function () {
    $unsigned = Signature::factory()->for($this->signature->contract)->create();

    expect($unsigned->signature_image_url)->toBeNull();
});
