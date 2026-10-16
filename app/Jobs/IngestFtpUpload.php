<?php

namespace App\Jobs;

use App\Events\PhotoAdded;
use App\Models\FtpAccount;
use App\Models\Gallery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        if (! $account?->active || ! $account->gallery) {
            return;
        }

        $disk = FtpAccount::disk();

        if (! $disk->exists($this->path)) {
            return;
        }

        $gallery = $account->gallery;
        $name = Gallery::sanitizeFileName(basename($this->path));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, config('picstome.upload_extensions'), true)) {
            $disk->delete($this->path);

            Log::info("FTP upload {$this->path} removed: unsupported file extension.");

            return;
        }

        if ($gallery->photos()->where('name', $name)->exists()) {
            $disk->delete($this->path);

            Log::info("FTP upload {$this->path} removed: a photo with this name already exists in gallery {$gallery->id}.");

            return;
        }

        $size = (int) $disk->size($this->path);

        if (! $gallery->team->canStoreFile($size)) {
            Log::warning("FTP upload {$this->path} skipped: team {$gallery->team_id} does not have enough storage. File left in inbox.");

            return;
        }

        $targetPath = $gallery->storage_path.'/'.$name;

        $disk->move($this->path, $targetPath);

        $photo = $gallery->photos()->create([
            'name' => $name,
            'size' => $size,
            'path' => $targetPath,
            'disk' => config('picstome.disk'),
            'status' => 'pending',
        ]);

        ProcessPhoto::dispatch($photo);

        PhotoAdded::dispatch($photo);

        $account->update(['last_upload_at' => now()]);
    }
}
