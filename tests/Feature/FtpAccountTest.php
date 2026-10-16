<?php

use App\Models\FtpAccount;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates numeric only credentials that are easy to enter on a camera', function () {
    $code = FtpAccount::generateNumericCode(6);
    $password = FtpAccount::generateNumericCode(8);

    expect($code)->toMatch('/^\d{6}$/');
    expect($password)->toMatch('/^\d{8}$/');
});

it('provisions one account per team with stable credentials', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);

    $team = Team::factory()->create();

    $account = FtpAccount::provisionForTeam($team);

    expect($account->team_id)->toBe($team->id);
    expect($account->active)->toBeTrue();
    expect($account->username)->toMatch('/^\d{6}$/');
    expect($account->password)->toMatch('/^\d{8}$/');
    expect($account->activeGallery)->toBeNull();

    // A team only ever gets one account: provisioning again returns it.
    expect(FtpAccount::provisionForTeam($team->fresh())->id)->toBe($account->id);
});

it('resolves uploads to the active gallery and falls back to a dated shoot gallery', function () {
    $team = Team::factory()->create();
    $account = FtpAccount::factory()->for($team)->create();

    // No active shoot: a dated gallery is created once and reused.
    $resolved = $account->resolveActiveGallery();

    expect($resolved->name)->toBe(FtpAccount::shootName());
    expect($account->fresh()->active_gallery_id)->toBe($resolved->id);

    $resolvedAgain = $account->fresh()->resolveActiveGallery();

    expect($resolvedAgain->id)->toBe($resolved->id);
    expect($team->galleries()->count())->toBe(1);

    // An explicit active shoot always wins.
    $shoot = $team->galleries()->create(['name' => 'Wedding']);

    $account->routeTo($shoot);

    expect($account->routesTo($resolved))->toBeFalse();
    expect($account->fresh()->resolveActiveGallery()->id)->toBe($shoot->id);

    // Routing can be stopped.
    $account->routeTo(null);

    expect($account->fresh()->activeGallery)->toBeNull();
});

it('encrypts passwords at rest', function () {
    $account = FtpAccount::factory()->create(['password' => '12345678']);

    expect($account->password)->toBe('12345678');

    $rawPassword = \DB::table('ftp_accounts')->where('id', $account->id)->value('password');

    expect($rawPassword)->not->toBe('12345678');
    expect($rawPassword)->not->toContain('12345678');
});
