<?php

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use App\Models\Team;
use App\Services\FtpInboxIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * Put a fake camera upload in the account's inbox with the given age.
 */
function putInboxFile(FtpAccount $account, string $name, int $secondsOld): string
{
    $disk = Storage::disk('s3');
    $path = $account->inboxPath().'/'.$name;

    $disk->put($path, 'camera photo');
    touch($disk->path($path), time() - $secondsOld);

    return $path;
}

it('queues stable inbox files and skips files still transferring', function () {
    Queue::fake();

    $account = FtpAccount::factory()->create();
    $stable = putInboxFile($account, 'DSC_0001.JPG', 120);
    putInboxFile($account, 'DSC_0002.JPG', 0);

    $dispatched = app(FtpInboxIngestor::class)->dispatchStableUploads($account, 60);

    expect($dispatched)->toBe(1);
    Queue::assertPushed(IngestFtpUpload::class, fn ($job) => $job->path === $stable);
});

it('uses a short live window for completed s3 uploads', function () {
    Queue::fake();

    $account = FtpAccount::factory()->create();
    $path = putInboxFile($account, 'DSC_0001.JPG', 3);

    $ingestor = app(FtpInboxIngestor::class);

    expect($ingestor->liveScanStabilitySeconds())->toBe(2);
    expect($ingestor->dispatchStableUploads($account, $ingestor->liveScanStabilitySeconds()))->toBe(1);
    Queue::assertPushed(IngestFtpUpload::class, fn ($job) => $job->path === $path);
});

it('does not scan an account while another scan holds its lock', function () {
    Queue::fake();

    $account = FtpAccount::factory()->create();
    putInboxFile($account, 'DSC_0001.JPG', 120);

    $lock = Cache::lock("ftp-account:{$account->id}:scan", 30);

    expect($lock->get())->toBeTrue();

    expect(app(FtpInboxIngestor::class)->dispatchStableUploads($account, 60))->toBe(0);
    Queue::assertNotPushed(IngestFtpUpload::class);

    $lock->release();

    expect(app(FtpInboxIngestor::class)->dispatchStableUploads($account, 60))->toBe(1);
    Queue::assertPushed(IngestFtpUpload::class);
});

it('scans only active accounts from the scheduled ingestor', function () {
    Queue::fake();

    $active = FtpAccount::factory()->create();
    $inactive = FtpAccount::factory()->for(Team::factory())->create(['active' => false]);

    putInboxFile($active, 'DSC_0001.JPG', 120);
    putInboxFile($inactive, 'DSC_0002.JPG', 120);

    expect(app(FtpInboxIngestor::class)->dispatchAll())->toBe(1);

    Queue::assertPushed(IngestFtpUpload::class, fn ($job) => $job->ftpAccountId === $active->id);
    Queue::assertNotPushed(IngestFtpUpload::class, fn ($job) => $job->ftpAccountId === $inactive->id);
});
