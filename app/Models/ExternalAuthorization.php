<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use browser authorization for an external client.
 *
 * Only the SHA-256 hash of the code is persisted. The plain code travels from
 * the browser to the client through the deep link and is exchanged once.
 */
class ExternalAuthorization extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()
            ->where('code_hash', hash('sha256', $code))
            ->first();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
