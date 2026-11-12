<?php

namespace App\Models;

use App\Notifications\CameraUploadsPaused;
use App\Services\SftpgoService;
use Database\Factories\FtpAccountFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FtpAccount extends Model
{
    /** @use HasFactory<FtpAccountFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'password' => 'encrypted',
            'active' => 'boolean',
            'last_upload_at' => 'datetime',
            'quota_paused_at' => 'datetime',
            'quota_paused_notified_at' => 'datetime',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function activeGallery()
    {
        return $this->belongsTo(Gallery::class, 'active_gallery_id');
    }

    /**
     * Whether camera uploads are paused because the team ran out of storage.
     */
    public function isQuotaPaused(): bool
    {
        return $this->quota_paused_at !== null;
    }

    /**
     * Pause the account after a rejected upload.
     *
     * Returns true only for the transition, so the caller can notify the
     * photographer once per pause instead of once per discarded shot.
     */
    public function pauseForQuota(): bool
    {
        if ($this->isQuotaPaused()) {
            return false;
        }

        $this->forceFill(['quota_paused_at' => now()])->save();

        return true;
    }

    /**
     * Resume camera uploads once there is space again. The notification flag
     * is cleared too, so the next pause informs the photographer again.
     */
    public function resumeFromQuota(): void
    {
        if (! $this->isQuotaPaused()) {
            return;
        }

        $this->forceFill([
            'quota_paused_at' => null,
            'quota_paused_notified_at' => null,
        ])->save();
    }

    /**
     * Mark that the "uploads paused" email was sent for the current pause.
     */
    public function markQuotaPauseNotified(): void
    {
        if ($this->quota_paused_notified_at !== null) {
            return;
        }

        $this->forceFill(['quota_paused_notified_at' => now()])->save();
    }

    /**
     * Delete every file still waiting in the inbox. Returns how many were
     * removed.
     *
     * Called when the account is paused: files that arrived in the same burst
     * as the rejected one would otherwise stay on S3 indefinitely.
     */
    public function purgeInbox(): int
    {
        $disk = static::disk();
        $deleted = 0;

        foreach ($disk->getDriver()->listContents($this->inboxPath(), true) as $item) {
            if (! $item->isFile()) {
                continue;
            }

            if ($disk->delete($item->path())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Tell the photographer that camera uploads stopped because the team ran
     * out of storage. Sent once per pause, with the number of discarded shots.
     */
    public function notifyOwnerOfQuotaPause(int $discardedFiles): void
    {
        $owner = $this->team?->owner;

        if (! $owner) {
            return;
        }

        $owner->notify(new CameraUploadsPaused($this, $discardedFiles));

        $this->markQuotaPauseNotified();
    }

    /**
     * Generate a numeric-only code: the easiest thing to enter on a camera
     * dial (single keyboard, no case, no letter lookalikes). Usernames are
     * 6 digits, passwords 8. Brute force is throttled by SFTPGo's defender.
     */
    public static function generateNumericCode(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    /**
     * Create the team's account with generated credentials and provision it
     * on the SFTPGo server, rolling the account back when provisioning
     * fails. Credentials are stable for the team's lifetime, so cameras
     * only ever need to be configured once.
     */
    public static function provisionForTeam(Team $team): ?static
    {
        if ($existing = $team->ftpAccount()->first()) {
            return $existing;
        }

        $account = static::create([
            'team_id' => $team->id,
            'username' => static::generateUniqueUsername(),
            'password' => static::generateNumericCode(8),
            'active' => true,
        ]);

        try {
            app(SftpgoService::class)->createUser($account);
        } catch (\RuntimeException $e) {
            report($e);

            $account->delete();

            return null;
        }

        return $account;
    }

    protected static function generateUniqueUsername(): string
    {
        do {
            $username = static::generateNumericCode(6);
        } while (static::query()->where('username', $username)->exists());

        return $username;
    }

    /**
     * Rotate the account's username and password to freshly generated
     * numeric credentials, replacing the SFTPGo user and moving any files
     * still waiting in the old inbox. Routing state is preserved. Rolls
     * back when provisioning fails.
     */
    public function rotateCredentials(): bool
    {
        $oldAccount = clone $this;
        $oldUsername = $this->username;
        $oldPassword = $this->password;

        $this->update([
            'username' => static::generateUniqueUsername(),
            'password' => static::generateNumericCode(8),
        ]);

        try {
            app(SftpgoService::class)->deleteUser($oldAccount);
            app(SftpgoService::class)->createUser($this);
        } catch (\RuntimeException $e) {
            report($e);

            $this->update([
                'username' => $oldUsername,
                'password' => $oldPassword,
            ]);

            return false;
        }

        $disk = static::disk();

        // Preserve subfolders created by cameras (for example queen/ or
        // DCIM/100CANON/) when moving pending uploads to the new inbox.
        foreach ($disk->getDriver()->listContents($oldAccount->inboxPath(), true) as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $relativePath = Str::after($item->path(), $oldAccount->inboxPath().'/');
            $targetPath = $this->inboxPath().'/'.$relativePath;

            $disk->makeDirectory(dirname($targetPath));
            $disk->move($item->path(), $targetPath);
        }

        return true;
    }

    /**
     * The name used for a gallery created for a shoot on the given day.
     */
    public static function shootName(): string
    {
        return __('Shoot :date', ['date' => now()->format('Y-m-d')]);
    }

    /**
     * The shoot gallery name in the team owner's language, so names are
     * consistent regardless of who triggered the creation (web request or
     * the ingest command, which runs without a user locale).
     */
    public static function shootNameFor(Team $team): string
    {
        $previous = app()->getLocale();

        app()->setLocale($team->owner?->language ?: $previous);

        try {
            return static::shootName();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * The gallery the FTP ingest created for the team's current day, if any.
     * The date is the stable identity, independent of the localized name.
     */
    public static function dailyGalleryFor(Team $team): ?Gallery
    {
        return $team->galleries()
            ->whereDate('daily_shoot_date', today())
            ->first();
    }

    /**
     * An empty shoot gallery created earlier today: the auto-created daily
     * gallery or a gallery named after today's shoot. Only empty galleries
     * qualify, so a session that already contains photos is never reused.
     */
    public static function emptyDailyGalleryFor(Team $team): ?Gallery
    {
        if ($gallery = static::dailyGalleryFor($team)) {
            if ($gallery->photos()->doesntExist()) {
                return $gallery;
            }
        }

        $name = static::shootNameFor($team);

        return $team->galleries()
            ->whereDate('created_at', today())
            ->where('is_public', false)
            ->whereDoesntHave('photos')
            ->where(function ($query) use ($name) {
                $query->where('name', $name)
                    ->orWhere('name', 'like', $name.' (%');
            })
            ->latest('id')
            ->first();
    }

    /**
     * The gallery receiving this team's uploads: the active shoot, or the
     * single dated gallery created (or reused) for today when no shoot is
     * active. Creation is serialized and keyed by team + date so parallel
     * uploads and the webhook/scheduler never spawn duplicates.
     */
    public function resolveActiveGallery(): Gallery
    {
        return Cache::lock("ftp-account:{$this->id}:active-gallery", 60)
            ->block(10, function (): Gallery {
                $this->refresh();

                if ($gallery = $this->activeGallery) {
                    return $gallery;
                }

                $gallery = static::dailyGalleryFor($this->team);

                if (! $gallery) {
                    $gallery = static::emptyDailyGalleryFor($this->team);

                    if ($gallery) {
                        $gallery->update(['daily_shoot_date' => today()->toDateString()]);
                    }
                }

                if (! $gallery) {
                    $gallery = $this->team->galleries()->firstOrCreate(
                        ['daily_shoot_date' => today()],
                        [
                            'name' => static::shootNameFor($this->team),
                            'is_public' => false,
                            'keep_original_size' => (bool) ($this->team->owner->galleryDefaults()['keep_original_size'] ?? true),
                        ],
                    );
                }

                $this->update(['active_gallery_id' => $gallery->id]);

                return $gallery;
            });
    }

    /**
     * Route uploads to the given gallery, or stop routing when null.
     */
    public function routeTo(?Gallery $gallery): void
    {
        $this->update(['active_gallery_id' => $gallery?->id]);
    }

    /**
     * Whether uploads currently land in the given gallery.
     */
    public function routesTo(Gallery $gallery): bool
    {
        return $this->active_gallery_id === $gallery->id;
    }

    /**
     * Path on the storage disk where the camera's files land.
     */
    public function inboxPath(): string
    {
        return rtrim(config('picstome.ftp_uploads.inbox_prefix', 'camera-uploads'), '/').'/'.$this->username;
    }

    /**
     * Resolve an SFTPGo path to the full inbox key, preserving subfolders.
     *
     * SFTPGo reports virtual paths such as /queen/DSC_0001.JPG. The path is
     * relative to the account's home, which is the inbox root.
     */
    public function inboxPathFor(string $path): string
    {
        $path = ltrim(trim($path), '/');

        if ($path === '') {
            return $this->inboxPath();
        }

        $inbox = $this->inboxPath();

        if (str_starts_with($path, $inbox.'/')) {
            return $path;
        }

        return $inbox.'/'.$path;
    }

    /**
     * The storage disk where the camera uploads land.
     */
    public static function diskName(): string
    {
        return (string) (config('picstome.ftp_uploads.disk') ?: config('picstome.disk'));
    }

    /**
     * The driver behind the inbox disk. S3 objects are only listed once the
     * upload is complete, so the live scan can use a shorter stability window.
     */
    public static function diskDriver(): ?string
    {
        return config('filesystems.disks.'.static::diskName().'.driver');
    }

    /**
     * The disk the camera uploads are written to.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(static::diskName());
    }
}
