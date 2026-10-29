<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempRoot = storage_path('framework/testing/temp-cleanup-'.uniqid());

    $this->uploadPath = $this->tempRoot.'/uploads';
    $this->archivePath = $this->tempRoot.'/archives';
    $this->legacyArchivePath = $this->tempRoot.'/legacy-archives';
    $this->photoPath = $this->tempRoot.'/photos';

    foreach ([$this->uploadPath, $this->archivePath, $this->legacyArchivePath, $this->photoPath] as $path) {
        File::makeDirectory($path, 0755, true);
    }

    config([
        'picstome.temp_cleanup.enabled' => true,
        'picstome.temp_cleanup.upload_path' => $this->uploadPath,
        'picstome.temp_cleanup.upload_max_age_hours' => 24,
        'picstome.temp_cleanup.archive_max_age_hours' => 6,
        'picstome.temp_cleanup.photo_path' => $this->photoPath,
        'picstome.temp_cleanup.photo_max_age_hours' => 6,
        'picstome.temp_cleanup.legacy_archive_path' => $this->legacyArchivePath,
        'picstome.gallery_archives.temp_path' => $this->archivePath,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->tempRoot);
});

it('keeps stale temporary files during a dry run', function () {
    $oldUpload = createTempCleanupFile($this->uploadPath.'/phpji2m6n98dic019VezLn', 48);
    $oldArchive = createTempCleanupFile($this->archivePath.'/gallery-archive-old', 12);
    $oldLegacyArchive = createTempCleanupFile($this->legacyArchivePath.'/gallery-archive-legacy', 12);
    $oldPhoto = createTempCleanupFile($this->photoPath.'/old.jpg', 12);
    $phpLog = createTempCleanupFile($this->uploadPath.'/php_errors.log', 48);
    $phpScript = createTempCleanupFile($this->uploadPath.'/phpcheck.sh', 48);

    $this->artisan('temp:cleanup', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing was deleted (4 file(s)')
        ->assertSuccessful();

    expect(File::exists($oldUpload))->toBeTrue()
        ->and(File::exists($oldArchive))->toBeTrue()
        ->and(File::exists($oldLegacyArchive))->toBeTrue()
        ->and(File::exists($oldPhoto))->toBeTrue()
        ->and(File::exists($phpLog))->toBeTrue()
        ->and(File::exists($phpScript))->toBeTrue();
});

it('deletes only stale temporary files when forced', function () {
    $oldUpload = createTempCleanupFile($this->uploadPath.'/phpji2m6n98dic019VezLn', 48);
    $oldArchive = createTempCleanupFile($this->archivePath.'/gallery-archive-old', 12);
    $oldLegacyArchive = createTempCleanupFile($this->legacyArchivePath.'/gallery-archive-legacy', 12);
    $oldPhoto = createTempCleanupFile($this->photoPath.'/old.jpg', 12);
    $phpLog = createTempCleanupFile($this->uploadPath.'/php_errors.log', 48);
    $phpScript = createTempCleanupFile($this->uploadPath.'/phpcheck.sh', 48);

    $newUpload = createTempCleanupFile($this->uploadPath.'/phpnew0000000000000000', 0);
    $newArchive = createTempCleanupFile($this->archivePath.'/gallery-archive-new', 0);
    $newPhoto = createTempCleanupFile($this->photoPath.'/new.jpg', 0);

    $this->artisan('temp:cleanup', ['--force' => true])
        ->expectsOutputToContain('Deleted 4 file(s)')
        ->assertSuccessful();

    expect(File::exists($oldUpload))->toBeFalse()
        ->and(File::exists($oldArchive))->toBeFalse()
        ->and(File::exists($oldLegacyArchive))->toBeFalse()
        ->and(File::exists($oldPhoto))->toBeFalse()
        ->and(File::exists($phpLog))->toBeTrue()
        ->and(File::exists($phpScript))->toBeTrue()
        ->and(File::exists($newUpload))->toBeTrue()
        ->and(File::exists($newArchive))->toBeTrue()
        ->and(File::exists($newPhoto))->toBeTrue();
});

it('requires an explicit dry run or force option', function () {
    $this->artisan('temp:cleanup')->assertFailed();
});

function createTempCleanupFile(string $path, int $ageHours): string
{
    File::put($path, 'temporary');
    touch($path, now()->subHours($ageHours)->timestamp);

    return $path;
}
