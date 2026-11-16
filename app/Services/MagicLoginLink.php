<?php

namespace App\Services;

use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\LoginLink;
use App\Support\SafeRedirect;

/**
 * Creates and consumes passwordless login links.
 *
 * Only the SHA-256 hash of each token is stored. A link stays valid until it
 * expires (15 minutes for login requests, a day for transactional emails), so
 * email prefetchers and repeated clicks do not break it. Tokens can be revoked
 * on email or password changes and are pruned once expired.
 */
class MagicLoginLink
{
    public const TTL_LOGIN = 15;

    public const TTL_EMAIL = 24 * 60;

    /**
     * Create a token for the user and return the emailed URL.
     */
    public function for(
        User $user,
        ?string $redirect = null,
        int $ttlMinutes = self::TTL_LOGIN,
        ?string $ip = null,
        ?string $userAgent = null,
    ): string {
        $token = bin2hex(random_bytes(32));

        $this->pruneExpiredFor($user);

        MagicLoginToken::create([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'redirect' => SafeRedirect::sanitize($redirect),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => now()->addMinutes(max(1, $ttlMinutes)),
        ]);

        return route('login.magic', ['token' => $token]);
    }

    /**
     * Send the login link email.
     */
    public function send(User $user, ?string $redirect = null, int $ttlMinutes = self::TTL_LOGIN): void
    {
        $user->notify(new LoginLink($this->for(
            $user,
            $redirect,
            $ttlMinutes,
            request()->ip(),
            request()->userAgent(),
        )));
    }

    /**
     * Find a token that has not expired yet.
     */
    public function find(string $token): ?MagicLoginToken
    {
        if (! $this->isToken($token)) {
            return null;
        }

        return MagicLoginToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Mark the token as used and return it. The link itself remains valid
     * until it expires, so a second click (or an email scanner) still works.
     */
    public function consume(string $token): ?MagicLoginToken
    {
        $record = $this->find($token);

        if ($record === null) {
            return null;
        }

        $record->forceFill(['used_at' => now()])->save();

        return $record;
    }

    /**
     * Delete expired tokens.
     */
    public function prune(): int
    {
        return MagicLoginToken::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * Revoke every pending link for a user (email or password changes).
     */
    public function revokeFor(User $user): void
    {
        MagicLoginToken::query()->where('user_id', $user->getKey())->delete();
    }

    private function pruneExpiredFor(User $user): void
    {
        MagicLoginToken::query()
            ->where('user_id', $user->getKey())
            ->where('expires_at', '<=', now())
            ->delete();
    }

    private function isToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }
}
