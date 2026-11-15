<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Reads the GA4 configuration and the consent state shared by the marketing
 * site. The app never writes analytics cookies; WordPress owns that decision.
 */
class AnalyticsService
{
    public function enabled(): bool
    {
        return (bool) config('analytics.enabled');
    }

    public function measurementId(): ?string
    {
        $id = trim((string) config('analytics.ga4.measurement_id'));

        return $id !== '' ? $id : null;
    }

    public function apiSecret(): ?string
    {
        $secret = trim((string) config('analytics.ga4.api_secret'));

        return $secret !== '' ? $secret : null;
    }

    /**
     * Whether the current request may load GA4.
     *
     * Tracking requires the production flag, a measurement ID and an explicit
     * analytics consent. Direct visitors without the WordPress cookie are not
     * tracked; they are still counted by the first-party report.
     */
    public function shouldTrack(Request $request): bool
    {
        return $this->enabled()
            && $this->measurementId() !== null
            && $this->hasAnalyticsConsent($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function consent(Request $request): array
    {
        $consent = $this->decodeJson($request->cookie((string) config('analytics.consent_cookie')));

        if (! is_array($consent) && config('analytics.treat_attribution_cookie_as_consent')) {
            $attribution = $this->decodeJson($request->cookie((string) config('analytics.attribution_cookie')));

            if (is_array($attribution)) {
                $consent = ['analytics' => true];
            }
        }

        return is_array($consent) ? $consent : [];
    }

    public function hasAnalyticsConsent(Request $request): bool
    {
        return filter_var($this->consent($request)['analytics'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * GA4 client id from the first-party _ga cookie, shared across the
     * picstome.com subdomains. Used only by the server-side purchase backup.
     */
    public function clientId(Request $request): ?string
    {
        $cookie = (string) $request->cookie('_ga');
        $parts = explode('.', $cookie);

        if (count($parts) < 4) {
            return null;
        }

        $clientId = implode('.', array_slice($parts, -2));

        return preg_match('/^\d+\.\d+$/', $clientId) === 1 ? $clientId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(urldecode($value), true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
