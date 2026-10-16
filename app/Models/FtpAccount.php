<?php

namespace App\Models;

use App\Services\SftpgoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FtpAccount extends Model
{
    /** @use HasFactory<\Database\Factories\FtpAccountFactory> */
    use HasFactory;

    /**
     * Camera-friendly alphabet: digits and letters without ambiguous
     * lookalikes (0/O, 1/I/l), since credentials are typed on camera
     * bodies using a dial.
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

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Generate a random camera-friendly code of the given length.
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
     * Create a new account with generated credentials for the given gallery.
     */
    public static function generateForGallery(Gallery $gallery): static
    {
        do {
            $username = static::generateCode();
        } while (static::query()->where('username', $username)->exists());

        return static::create([
            'gallery_id' => $gallery->id,
            'username' => $username,
            'password' => static::generateCode(10),
            'active' => true,
        ]);
    }

    /**
     * Create an account with generated credentials for the given gallery and
     * provision it on the SFTPGo server, rolling the account back when
     * provisioning fails.
     */
    public static function provisionForGallery(Gallery $gallery): ?static
    {
        $account = static::generateForGallery($gallery);

        try {
            app(SftpgoService::class)->createUser($account);
        } catch (\RuntimeException $e) {
            report($e);

            $account->delete();

            return null;
        }

        return $account;
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
