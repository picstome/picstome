<?php

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\User;
use App\Notifications\CameraUploadsPaused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    config([
        'picstome.ftp_uploads.inbox_prefix' => 'camera-uploads',
        'picstome.ftp_uploads.sftpgo_url' => null,
    ]);

    Notification::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['custom_storage_limit' => 1024]);
});

function cameraAccount($team): FtpAccount
{
    return FtpAccount::factory()->for($team)->create([
        'active' => true,
        'active_gallery_id' => Gallery::factory()->for($team)->create()->id,
    ]);
}

it('deletes the rejected file and pauses the account when storage is full', function () {
    $account = cameraAccount($this->team);

    $path = $account->inboxPath().'/IMG_0001.JPG';
    Storage::disk('s3')->put($path, str_repeat('x', 2048));

    (new IngestFtpUpload($account->id, $path))->handle();

    Storage::disk('s3')->assertMissing($path);
    expect($account->fresh()->isQuotaPaused())->toBeTrue();
});

it('notifies the photographer once per pause and lists the discarded files', function () {
    $account = cameraAccount($this->team);

    $path = $account->inboxPath().'/IMG_0001.JPG';
    Storage::disk('s3')->put($path, str_repeat('x', 2048));

    (new IngestFtpUpload($account->id, $path))->handle();

    Notification::assertSentTo($this->team->owner, CameraUploadsPaused::class, function ($notification) {
        return $notification->discardedFiles >= 1;
    });

    Notification::fake();

    // A second rejected shot must not send another email for the same pause.
    $second = $account->inboxPath().'/IMG_0002.JPG';
    Storage::disk('s3')->put($second, str_repeat('x', 2048));
    (new IngestFtpUpload($account->id, $second))->handle();

    Notification::assertNothingSent();
});

it('skips paused accounts when scanning the inbox', function () {
    $account = cameraAccount($this->team);
    $account->pauseForQuota();

    $path = $account->inboxPath().'/IMG_0001.JPG';
    Storage::disk('s3')->put($path, str_repeat('x', 2048));

    artisan('ingest:ftp-uploads')->assertExitCode(0);

    Storage::disk('s3')->assertExists($path);
});

it('resumes camera uploads once there is space again', function () {
    $account = cameraAccount($this->team);

    $rejected = $account->inboxPath().'/IMG_0001.JPG';
    Storage::disk('s3')->put($rejected, str_repeat('x', 2048));
    (new IngestFtpUpload($account->id, $rejected))->handle();

    expect($account->fresh()->isQuotaPaused())->toBeTrue();

    $this->team->update(['custom_storage_limit' => 1073741824]);

    // Processing is not what this test covers: only the resume transition.
    Queue::fake();

    $accepted = $account->inboxPath().'/IMG_0002.JPG';
    Storage::disk('s3')->put($accepted, str_repeat('x', 10));
    (new IngestFtpUpload($account->id, $accepted))->handle();

    expect($account->fresh()->isQuotaPaused())->toBeFalse();
    Storage::disk('s3')->assertMissing($accepted);
});

it('purges the whole inbox when pausing', function () {
    $account = cameraAccount($this->team);

    foreach (['A.JPG', 'B.JPG', 'C.JPG'] as $name) {
        Storage::disk('s3')->put($account->inboxPath().'/'.$name, str_repeat('x', 2048));
    }

    (new IngestFtpUpload($account->id, $account->inboxPath().'/A.JPG'))->handle();

    expect($account->fresh()->isQuotaPaused())->toBeTrue();
    expect(Storage::disk('s3')->allFiles($account->inboxPath()))->toBe([]);
});

it('deletes incoming webhook files while the account is paused', function () {
    $account = cameraAccount($this->team);
    $account->pauseForQuota();

    config(['picstome.ftp_uploads.webhook_token' => 'secret']);

    $path = $account->inboxPath().'/LATE.JPG';
    Storage::disk('s3')->put($path, str_repeat('x', 10));

    $this->postJson('/webhooks/sftpgo', [
        'action' => 'upload',
        'username' => $account->username,
        'virtual_path' => 'LATE.JPG',
        'status' => 1,
        'file_size' => 10,
    ], ['X-PICSTOME-TOKEN' => 'secret'])->assertNoContent();

    Storage::disk('s3')->assertMissing($path);
});

it('cleans stale inbox files with the force flag only', function () {
    $account = cameraAccount($this->team);

    $path = $account->inboxPath().'/OLD.JPG';
    Storage::disk('s3')->put($path, str_repeat('x', 10));

    artisan('ftp:cleanup-inbox')->assertExitCode(1);

    artisan('ftp:cleanup-inbox --dry-run')->assertExitCode(0);
    Storage::disk('s3')->assertExists($path);

    // The stability window protects transfers that are still in progress.
    artisan('ftp:cleanup-inbox --force')->assertExitCode(0);
    Storage::disk('s3')->assertExists($path);
});
