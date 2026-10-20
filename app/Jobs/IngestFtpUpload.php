<?php

namespace App\Jobs;

use App\Events\PhotoAdded;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IngestFtpUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $ftpAccountId,
        public string $path,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $account = FtpAccount::find($this->ftpAccountId);

        if (! $account?->active || ! $account->team) {
            return;
        }

        $disk = FtpAccount::disk();

        if (! $disk->exists($this->path)) {
            return;
        }

        $gallery = $account->activeGallery;

        if (! $gallery) {
            return;
        }

        $name = Gallery::sanitizeFileName(basename($this->path));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, config('picstome.upload_extensions'), true)) {
            $disk->delete($this->path);

            Log::info("FTP upload {$this->path} removed: unsupported file extension.");

            return;
        }

        $size = (int) $disk->size($this->path);

        if (! $gallery->team->canStoreFile($size)) {
            Log::warning("FTP upload {$this->path} skipped: team {$gallery->team_id} does not have enough storage. File left in inbox.");

            return;
        }

        $photo = $gallery->withPhotoIdentityLock(Gallery::identityKey($name), function () use ($gallery, $disk, $name, $size): ?Photo {
            $existingPhoto = $gallery->findLogicalPhotoBySourceName($name);

            if ($existingPhoto && ! config('picstome.file_versions_enabled')) {
                Log::warning("FTP upload {$this->path} skipped: file versions are disabled.");

                return null;
            }

            $targetPath = $gallery->storage_path.'/'.Str::ulid().'-'.$name;

            $disk->move($this->path, $targetPath);

            return $existingPhoto
                ? $gallery->addVersionFromStoredPath($existingPhoto, $targetPath, $name, $size)
                : $gallery->addPhotoFromStoredPath($targetPath, $name, $size);
        });

        if (! $photo) {
            return;
        }

        PhotoAdded::dispatch($photo);

        $account->update(['last_upload_at' => now()]);
    }
}
