<?php

use App\Events\PhotoAdded;
use App\Jobs\IngestFtpUpload;
use App\Jobs\ProcessPhoto;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);

    Storage::fake('s3');

    $this->gallery = Gallery::factory()->create(['ulid' => 'LIVEGALLERY']);
    $this->account = FtpAccount::factory()->for($this->gallery)->create([
        'username' => 'AB23CD45',
    ]);
});

/**
 * Put a fake camera upload in the account's inbox, backdated past the
 * stability window.
 */
function putUpload(FtpAccount $account, string $name, int $secondsOld = 120): string
{
    $disk = Storage::disk('s3');

    $path = $account->inboxPath().'/'.$name;

    $disk->put($path, 'fake photo contents');

    touch($disk->path($path), time() - $secondsOld);

    return $path;
}

describe('ingest command', function () {
    it('dispatches ingestion jobs for stable uploads from active accounts', function () {
        Queue::fake();

        $path = putUpload($this->account, 'DSC_0001.JPG');

        $this->artisan('ingest:ftp-uploads');

        Queue::assertPushed(IngestFtpUpload::class, fn ($job) => $job->ftpAccountId === $this->account->id
            && $job->path === $path);
    });

    it('skips files that are still transferring', function () {
        Queue::fake();

        putUpload($this->account, 'DSC_0001.JPG', 0);

        $this->artisan('ingest:ftp-uploads');

        Queue::assertNotPushed(IngestFtpUpload::class);
    });

    it('ignores inactive accounts', function () {
        Queue::fake();

        $this->account->update(['active' => false]);

        putUpload($this->account, 'DSC_0001.JPG');

        $this->artisan('ingest:ftp-uploads');

        Queue::assertNotPushed(IngestFtpUpload::class);
    });
});

describe('ingest job', function () {
    it('moves an uploaded photo into its gallery and schedules processing', function () {
        Queue::fake();
        Event::fake(PhotoAdded::class);

        $path = putUpload($this->account, 'DSC_0001.JPG');

        (new IngestFtpUpload($this->account->id, $path))->handle();

        tap($this->gallery->fresh()->photos()->first(), function ($photo) {
            expect($photo)->not->toBeNull();
            expect($photo->name)->toBe('DSC_0001.JPG');
            expect($photo->disk)->toBe('s3');
            expect($photo->status)->toBe('pending');
            expect($photo->size)->toBeGreaterThan(0);
            expect($photo->path)->toBe($this->gallery->storage_path.'/DSC_0001.JPG');
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
        });

        expect(Storage::disk('s3')->exists($path))->toBeFalse();

        Queue::assertPushed(ProcessPhoto::class);
        Event::assertDispatched(PhotoAdded::class);
        expect($this->account->fresh()->last_upload_at)->not->toBeNull();
    });

    it('removes uploads with unsupported extensions', function () {
        $path = putUpload($this->account, 'virus.exe');

        (new IngestFtpUpload($this->account->id, $path))->handle();

        expect($this->gallery->photos()->count())->toBe(0);
        expect(Storage::disk('s3')->exists($path))->toBeFalse();
    });

    it('removes duplicate uploads without touching the existing photo', function () {
        $existing = Photo::factory()->for($this->gallery)->create(['name' => 'DSC_0001.JPG']);

        $path = putUpload($this->account, 'DSC_0001.JPG');

        (new IngestFtpUpload($this->account->id, $path))->handle();

        expect($this->gallery->photos()->count())->toBe(1);
        expect($existing->fresh()->name)->toBe('DSC_0001.JPG');
        expect(Storage::disk('s3')->exists($path))->toBeFalse();
    });

    it('leaves the file in the inbox when the team is out of storage', function () {
        $this->gallery->team->update(['custom_storage_limit' => 1]);

        $path = putUpload($this->account, 'DSC_0001.JPG');

        (new IngestFtpUpload($this->account->id, $path))->handle();

        expect($this->gallery->photos()->count())->toBe(0);
        expect(Storage::disk('s3')->exists($path))->toBeTrue();
    });

    it('does nothing for inactive accounts', function () {
        $this->account->update(['active' => false]);

        $path = putUpload($this->account, 'DSC_0001.JPG');

        (new IngestFtpUpload($this->account->id, $path))->handle();

        expect($this->gallery->photos()->count())->toBe(0);
        expect(Storage::disk('s3')->exists($path))->toBeTrue();
    });
});
