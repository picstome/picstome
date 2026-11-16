<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-use passwordless login link. Only the SHA-256 hash of the token is
 * stored; the plain token lives in the emailed URL and is consumed on POST.
 */
class MagicLoginToken extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token_hash',
        'redirect',
        'ip',
        'user_agent',
        'expires_at',
        'used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->expires_at->isFuture();
    }
}
