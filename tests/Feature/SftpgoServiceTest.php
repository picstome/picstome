<?php

use App\Models\FtpAccount;
use App\Services\SftpgoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configureSftpgo(): void
{
    config([
        'picstome.ftp_uploads.sftpgo_url' => 'https://sftpgo.test',
        'picstome.ftp_uploads.sftpgo_api_key' => 'secret-key',
    ]);
}

it('is disabled when not configured', function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);

    Http::fake();

    $service = app(SftpgoService::class);

    expect($service->isEnabled())->toBeFalse();
    expect($service->createUser(FtpAccount::factory()->create()))->toBeFalse();

    Http::assertNothingSent();
});

it('creates a user with the camera credentials', function () {
    configureSftpgo();

    config(['picstome.ftp_uploads.sftpgo_s3_backend' => false]);

    Http::fake();

    $account = FtpAccount::factory()->create([
        'username' => 'AB23CD45',
        'password' => 'XY9Z8W7V6U',
    ]);

    app(SftpgoService::class)->createUser($account);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-SFTPGO-API-KEY', 'secret-key')
            && $request->url() === 'https://sftpgo.test/api/v2/users'
            && $request['username'] === 'AB23CD45'
            && $request['password'] === 'XY9Z8W7V6U'
            && $request['status'] === 1
            && $request['permissions'] === ['/' => ['*']]
            && ! isset($request['home_dir'])
            && ! isset($request['filesystem']);
    });
});

it('attaches the s3 filesystem config when the backend is enabled', function () {
    configureSftpgo();

    config([
        'picstome.ftp_uploads.sftpgo_s3_backend' => true,
        'filesystems.disks.s3' => [
            'driver' => 's3',
            'bucket' => 'picstome-bucket',
            'region' => 'eu-central-1',
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret-s3',
            'endpoint' => null,
            'use_path_style_endpoint' => false,
        ],
    ]);

    Http::fake();

    $account = FtpAccount::factory()->create(['username' => 'AB23CD45']);

    app(SftpgoService::class)->createUser($account);

    Http::assertSent(function ($request) {
        return $request['filesystem']['provider'] === 1
            && $request['filesystem']['s3config']['bucket'] === 'picstome-bucket'
            && $request['filesystem']['s3config']['region'] === 'eu-central-1'
            && $request['filesystem']['s3config']['access_key'] === 'AKIAEXAMPLE'
            && $request['filesystem']['s3config']['access_secret'] === ['status' => 'Plain', 'payload' => 'secret-s3']
            && $request['filesystem']['s3config']['key_prefix'] === 'camera-uploads/AB23CD45/'
            && $request['filesystem']['s3config']['force_path_style'] === false;
    });
});

it('updates the user status when the account is toggled', function () {
    configureSftpgo();

    config(['picstome.ftp_uploads.sftpgo_s3_backend' => false]);

    Http::fake();

    $account = FtpAccount::factory()->inactive()->create(['username' => 'AB23CD45']);

    app(SftpgoService::class)->updateUser($account);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && $request->url() === 'https://sftpgo.test/api/v2/users/AB23CD45'
            && $request['status'] === 0;
    });
});

it('deletes the user', function () {
    configureSftpgo();

    Http::fake();

    $account = FtpAccount::factory()->create(['username' => 'AB23CD45']);

    app(SftpgoService::class)->deleteUser($account);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://sftpgo.test/api/v2/users/AB23CD45');
});

it('throws when the api responds with an error', function () {
    configureSftpgo();

    Http::fake([
        'sftpgo.test/*' => Http::response('boom', 500),
    ]);

    app(SftpgoService::class)->createUser(FtpAccount::factory()->create());
})->throws(RuntimeException::class);
