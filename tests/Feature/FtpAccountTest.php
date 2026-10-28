<?php

use App\Models\FtpAccount;
use App\Models\Photo;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

it('keeps one daily gallery regardless of the request or queue locale', function () {
    $team = Team::factory()->create();
    $account = FtpAccount::factory()->for($team)->create();

    app()->setLocale('es');

    $first = $account->resolveActiveGallery();

    expect($first->name)->toBe('Sesión '.now()->format('Y-m-d'));
    expect($first->daily_shoot_date->toDateString())->toBe(today()->toDateString());

    $account->routeTo(null);

    // The queue/CLI runs with the app locale, not the request locale.
    app()->setLocale('en');

    $second = $account->fresh()->resolveActiveGallery();

    expect($second->id)->toBe($first->id);
    expect($team->galleries()->count())->toBe(1);
});

it('adopts an empty shoot gallery created earlier the same day', function () {
    $team = Team::factory()->create();
    $existing = $team->galleries()->create([
        'name' => FtpAccount::shootName(),
        'is_public' => false,
    ]);

    $account = FtpAccount::factory()->for($team)->create();

    $resolved = $account->resolveActiveGallery();

    expect($resolved->id)->toBe($existing->id);
    expect($resolved->daily_shoot_date->toDateString())->toBe(today()->toDateString());
    expect($team->galleries()->count())->toBe(1);
});

it('does not reuse a completed shoot gallery as the daily gallery', function () {
    $team = Team::factory()->create();
    $existing = $team->galleries()->create([
        'name' => FtpAccount::shootName(),
        'is_public' => false,
    ]);

    Photo::factory()->for($existing)->create();

    $account = FtpAccount::factory()->for($team)->create();

    $resolved = $account->resolveActiveGallery();

    expect($resolved->id)->not->toBe($existing->id);
    expect($team->galleries()->count())->toBe(2);
});

it('encrypts passwords at rest', function () {
    $account = FtpAccount::factory()->create(['password' => '12345678']);

    expect($account->password)->toBe('12345678');

    $rawPassword = DB::table('ftp_accounts')->where('id', $account->id)->value('password');

    expect($rawPassword)->not->toBe('12345678');
    expect($rawPassword)->not->toContain('12345678');
});

it('rotates credentials to numeric ones while preserving team and routing', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Storage::fake('s3');

    $team = Team::factory()->create();
    $gallery = $team->galleries()->create(['name' => 'Wedding']);
    $account = FtpAccount::factory()->for($team)->create([
        'username' => 'JXQJSR2U',
        'active_gallery_id' => $gallery->id,
    ]);

    expect($account->rotateCredentials())->toBeTrue();

    tap($account->fresh(), function ($rotated) use ($gallery, $team) {
        expect($rotated->username)->toMatch('/^\d{6}$/');
        expect($rotated->password)->toMatch('/^\d{8}$/');
        expect($rotated->username)->not->toBe('JXQJSR2U');
        expect($rotated->team_id)->toBe($team->id);
        expect($rotated->routesTo($gallery))->toBeTrue();
    });
});

it('moves pending inbox files to the new inbox when rotating', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);
    Storage::fake('s3');

    $account = FtpAccount::factory()->create(['username' => 'JXQJSR2U']);

    Storage::disk('s3')->put($account->inboxPath().'/DSC_0001.JPG', 'pending');

    $account->rotateCredentials();

    $newPath = $account->fresh()->inboxPath().'/DSC_0001.JPG';

    expect(Storage::disk('s3')->exists($newPath))->toBeTrue();
    expect(Storage::disk('s3')->exists('camera-uploads/JXQJSR2U/DSC_0001.JPG'))->toBeFalse();
});

it('rolls back the rotation when the sftpgo server cannot be reached', function () {
    config([
        'picstome.ftp_uploads.sftpgo_url' => 'https://sftpgo.test',
        'picstome.ftp_uploads.sftpgo_api_key' => 'secret-key',
    ]);

    Http::fake([
        'sftpgo.test/*' => Http::response('boom', 500),
    ]);

    $account = FtpAccount::factory()->create(['username' => 'JXQJSR2U', 'password' => '12345678']);

    expect($account->rotateCredentials())->toBeFalse();

    tap($account->fresh(), function ($account) {
        expect($account->username)->toBe('JXQJSR2U');
        expect($account->password)->toBe('12345678');
    });

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/api/v2/users/JXQJSR2U'));
});
