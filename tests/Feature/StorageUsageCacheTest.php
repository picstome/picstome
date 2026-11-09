<?php

use App\Models\Gallery;
use App\Models\Invoice;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    StorageUsageService::flushRequestMemo();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('serves the team total from cache after the first calculation', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    $first = $this->team->calculateStorageUsed();

    expect($first)->toBe(1000);

    // A second read must not recompute: the row is changed behind the
    // service's back and the cached total keeps answering.
    $gallery->photos()->update(['stored_size' => 9999]);

    expect($this->team->calculateStorageUsed())->toBe(1000);
});

it('invalidates the cached total when a photo is created', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    expect($this->team->calculateStorageUsed())->toBe(0);

    Photo::factory()->for($gallery)->create(['stored_size' => 2500]);

    expect($this->team->calculateStorageUsed())->toBe(2500);
});

it('invalidates the cached total when stored bytes on a photo change', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    expect($this->team->calculateStorageUsed())->toBe(1000);

    $photo->update(['stored_size' => 4000]);

    expect($this->team->calculateStorageUsed())->toBe(4000);
});

it('invalidates the cached total when a photo is deleted', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    expect($this->team->calculateStorageUsed())->toBe(1000);

    $photo->delete();

    expect($this->team->calculateStorageUsed())->toBe(0);
});

it('invalidates the cached total when a retained version is written', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    expect($this->team->calculateStorageUsed())->toBe(1000);

    PhotoVersion::factory()->for($photo)->create(['stored_size' => 500]);

    expect($this->team->calculateStorageUsed())->toBe(1500);
});

it('invalidates the cached total when an invoice pdf size is stored', function () {
    expect($this->team->calculateStorageUsed())->toBe(0);

    $invoice = Invoice::factory()->for($this->team)->create(['pdf_size' => 0]);

    expect($this->team->calculateStorageUsed())->toBe(0);

    $invoice->update(['pdf_size' => 1200]);

    expect($this->team->calculateStorageUsed())->toBe(1200);
});

it('batches the totals for many teams in three grouped queries', function () {
    $otherUser = User::factory()->withPersonalTeam()->create();
    $otherTeam = $otherUser->currentTeam;

    $ownGallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($ownGallery)->create(['stored_size' => 300]);
    Photo::factory()->for($ownGallery)->count(2)->create(['stored_size' => 100]);

    $otherGallery = Gallery::factory()->for($otherTeam)->create();
    Photo::factory()->for($otherGallery)->create(['stored_size' => 900]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $totals = StorageUsageService::forTeams([$this->team->id, $otherTeam->id]);

    // One grouped aggregate per source: photos, versions, invoices.
    expect($queries)->toBe(3)
        ->and($totals[$this->team->id])->toBe(500)
        ->and($totals[$otherTeam->id])->toBe(900);
});

it('returns zero for a team without photos when batching', function () {
    $team = Team::factory()->create(['personal_team' => true]);

    $totals = StorageUsageService::forTeams([$team->id]);

    expect($totals[$team->id])->toBe(0);
});

it('does not leak a memoised total into the next request', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    expect($this->team->calculateStorageUsed())->toBe(1000);

    // The test case reset runs before each test; emulate a new request here.
    StorageUsageService::flushRequestMemo();

    Cache::forget("team:{$this->team->id}:storage_used");

    expect($this->team->calculateStorageUsed())->toBe(1000);
});

it('checks the quota against fresh bytes, not the cached display value', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    $this->team->update(['custom_storage_limit' => 2000]);

    // Warm the display cache with the small value.
    expect($this->team->calculateStorageUsed())->toBe(1000);

    // Bytes grow behind the cached read (a sync batch, a quiet update).
    $gallery->photos()->update(['stored_size' => 1900]);

    // A 200-byte upload must be rejected: 1000 + 200 would fit, 1900 + 200 does not.
    expect($this->team->fresh()->canStoreFile(200))->toBeFalse()
        ->and($this->team->fresh()->canStoreFile(50))->toBeTrue();

    // The fresh read also refreshes the memo, so the displayed total is right.
    expect($this->team->calculateStorageUsed())->toBe(1900);
});

it('counts a byte-exact upload at the limit as allowed', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['stored_size' => 1000]);

    $this->team->update(['custom_storage_limit' => 2000]);

    expect($this->team->fresh()->canStoreFile(1000))->toBeTrue()
        ->and($this->team->fresh()->canStoreFile(1001))->toBeFalse();
});
