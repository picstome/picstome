<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use DateTimeInterface;
use Stripe\StripeObject;

/**
 * Builds the attribution metadata attached to Stripe Checkout sessions,
 * subscriptions and payments.
 *
 * Only pseudonymous campaign data is sent. No name, email or other personal
 * data belongs in Stripe metadata: anyone with dashboard access can read it.
 */
class StripeAttributionMetadata
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'ps_user_id',
        'ps_source',
        'ps_medium',
        'ps_campaign',
        'ps_content',
        'ps_at',
        'ps_touch',
    ];

    /**
     * @param  array<string, mixed>|null  $attribution  Last-touch fallback for users created before attribution existed.
     * @return array<string, string>
     */
    public function forTeam(Team $team, ?array $attribution = null): array
    {
        return $this->forUser($team->owner, $attribution);
    }

    /**
     * @param  array<string, mixed>|null  $attribution
     * @return array<string, string>
     */
    public function forUser(?User $user, ?array $attribution = null): array
    {
        $fallback = is_array($attribution) ? $attribution : [];

        $source = $this->value($user?->attr_source) ?? $this->value($fallback['source'] ?? null);
        $medium = $this->value($user?->attr_medium) ?? $this->value($fallback['medium'] ?? null);
        $campaign = $this->value($user?->attr_campaign) ?? $this->value($fallback['campaign'] ?? null);
        $content = $this->value($user?->attr_content) ?? $this->value($fallback['content'] ?? null);

        $at = $user?->attr_at;

        if ($at === null && isset($fallback['at']) && $fallback['at'] instanceof DateTimeInterface) {
            $at = $fallback['at'];
        }

        return array_filter([
            'ps_user_id' => $user ? (string) $user->id : null,
            'ps_source' => $source,
            'ps_medium' => $medium,
            'ps_campaign' => $campaign,
            'ps_content' => $content,
            'ps_at' => $at?->toIso8601String(),
            'ps_touch' => $this->value($user?->attr_source) !== null ? 'signup' : 'checkout',
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Keep only the attribution keys already stored on a subscription.
     *
     * @return array<string, string>
     */
    public function fromMetadata(mixed $metadata): array
    {
        if ($metadata instanceof StripeObject) {
            $metadata = $metadata->toArray();
        }

        if (! is_array($metadata)) {
            return [];
        }

        $metadata = array_intersect_key($metadata, array_flip(self::KEYS));

        return array_filter(
            array_map(fn ($value): ?string => $this->value($value), $metadata),
            fn (?string $value): bool => $value !== null && $value !== '',
        );
    }

    private function value(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
