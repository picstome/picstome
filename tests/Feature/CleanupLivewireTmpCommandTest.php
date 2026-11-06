<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('tmp-for-tests');

    config([
        'livewire.temporary_file_upload.cleanup' => true,
    ]);
});

it('deletes livewire temporary uploads older than the threshold', function () {
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/old.jpg', 25);
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/old.jpg.json', 25);
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/new.jpg', 1);

    $this->artisan('cleanup:livewire-tmp', ['--force' => true])
        ->expectsOutputToContain('Deleted 2 file(s), kept 1 file(s), 0 failed.')
        ->assertSuccessful();

    Storage::disk('tmp-for-tests')->assertMissing('livewire-tmp/old.jpg');
    Storage::disk('tmp-for-tests')->assertMissing('livewire-tmp/old.jpg.json');
    Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/new.jpg');
});

it('keeps stale uploads during a dry run', function () {
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/old.jpg', 25);

    $this->artisan('cleanup:livewire-tmp', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: would delete 1 file(s), kept 0 file(s), 0 failed.')
        ->assertSuccessful();

    Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/old.jpg');
});

it('requires an explicit dry run or force option', function () {
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/old.jpg', 25);

    $this->artisan('cleanup:livewire-tmp')->assertFailed();

    Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/old.jpg');
});

it('uses the configured age threshold', function () {
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/two-hours.jpg', 2);
    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/fresh.jpg', 0);

    $this->artisan('cleanup:livewire-tmp', ['--hours' => 1, '--force' => true])
        ->assertSuccessful();

    Storage::disk('tmp-for-tests')->assertMissing('livewire-tmp/two-hours.jpg');
    Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/fresh.jpg');
});

it('can clean an explicitly selected disk', function () {
    Storage::fake('s3');

    createLivewireTmpUpload('s3', 'livewire-tmp/old.jpg', 25);

    $this->artisan('cleanup:livewire-tmp', ['disk' => 's3', '--force' => true])
        ->assertSuccessful();

    Storage::disk('s3')->assertMissing('livewire-tmp/old.jpg');
});

it('does nothing when livewire cleanup is disabled', function () {
    config(['livewire.temporary_file_upload.cleanup' => false]);

    createLivewireTmpUpload('tmp-for-tests', 'livewire-tmp/old.jpg', 25);

    $this->artisan('cleanup:livewire-tmp', ['--force' => true])
        ->expectsOutputToContain('Livewire temporary upload cleanup is disabled.')
        ->assertSuccessful();

    Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/old.jpg');
});

it('fails when the disk is not configured', function () {
    $this->artisan('cleanup:livewire-tmp', ['disk' => 'missing', '--force' => true])
        ->assertFailed();
});

function createLivewireTmpUpload(string $disk, string $path, int $ageHours): void
{
    $filesystem = Storage::disk($disk);
    $filesystem->put($path, 'temporary');
    touch($filesystem->path($path), now()->subHours($ageHours)->timestamp);
}
