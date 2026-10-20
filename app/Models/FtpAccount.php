<?php

namespace App\Models;

use App\Services\SftpgoService;
use Database\Factories\FtpAccountFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

        foreach ($disk->files($oldAccount->inboxPath()) as $path) {
            $disk->move($path, $this->inboxPath().'/'.basename($path));
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
     * The gallery receiving this team's uploads: the active shoot, or a
     * dated gallery created (or reused) for today when no shoot is active.
     */
    public function resolveActiveGallery(): Gallery
    {
        if ($gallery = $this->activeGallery) {
            return $gallery;
        }

        $name = static::shootNameFor($this->team);

        $gallery = $this->team->galleries()->where('name', $name)->first()
            ?? $this->team->galleries()->create(['name' => $name, 'is_public' => false]);

        $this->update(['active_gallery_id' => $gallery->id]);

        return $gallery;
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
     * The disk the camera uploads are written to.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(config('picstome.ftp_uploads.disk') ?: config('picstome.disk'));
    }
}
