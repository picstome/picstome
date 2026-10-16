<?php

namespace App\Models;

use App\Services\SftpgoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FtpAccount extends Model
{
    /** @use HasFactory<\Database\Factories\FtpAccountFactory> */
    use HasFactory;

    /**
     * Camera-friendly alphabet for usernames: digits and letters without
     * ambiguous lookalikes (0/O, 1/I/l), since credentials are typed on
     * camera bodies using a dial.
     */
    public const CAMERA_FRIENDLY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

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
     * Generate a random camera-friendly username fragment.
     */
    public static function generateCode(int $length = 8): string
    {
        $alphabet = self::CAMERA_FRIENDLY_ALPHABET;

        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /**
     * Generate a numeric-only password: the easiest thing to type on a
     * camera dial. Brute force is throttled by SFTPGo's defender.
     */
    public static function generateNumericPassword(int $length = 8): string
    {
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= (string) random_int(0, 9);
        }

        return $password;
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
            'password' => static::generateNumericPassword(),
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
            $username = static::generateCode();
        } while (static::query()->where('username', $username)->exists());

        return $username;
    }

    /**
     * The name used for a gallery created for a shoot on the given day.
     */
    public static function shootName(): string
    {
        return __('Shoot :date', ['date' => now()->format('Y-m-d')]);
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

        $name = static::shootName();

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
    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('picstome.ftp_uploads.disk') ?: config('picstome.disk'));
    }
}
