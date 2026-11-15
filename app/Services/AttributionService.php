<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Captures and stores the first visit that brought a user to Picstome.
 *
 * The marketing site writes a first-party cookie on .picstome.com with the
 * original touch (and the last campaign touch). The app only reads it, falls
 * back to a signed-out link parameter or the current URL, and persists the
 * result once, when the account is created.
 */
class AttributionService
{
    public const SESSION_KEY = 'attribution';

    private const MAX_FIELD = 100;

    private const MAX_URL = 2048;

    private const PARAM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gclid',
        'fbclid',
        'msclkid',
    ];

    /**
     * Read the current attribution, in priority order: cookie, session (for
     * the /subscribe → /register redirect), ps_aid link, current URL.
     *
     * @return array<string, mixed>|null
     */
    public function capture(Request $request): ?array
    {
        if ($fromCookie = $this->fromCookie($request)) {
            return $fromCookie;
        }

        if ($request->hasSession()) {
            $fromSession = $request->session()->get(self::SESSION_KEY);

            if (is_array($fromSession) && $fromSession !== []) {
                return $fromSession;
            }
        }

        return $this->fromAid($request) ?? $this->fromQuery($request);
    }

    /**
     * Remember a real signal for the following request. Direct visits are not
     * cached, so a later campaign click can still win.
     *
     * @return array<string, mixed>|null
     */
    public function remember(Request $request): ?array
    {
        $attribution = $this->capture($request);

        if ($attribution !== null && $request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $attribution);
        }

        return $attribution;
    }

    /**
     * Write the attribution once. Existing values are never overwritten and
     * logins never reach this method.
     */
    public function persist(User $user, Request $request): void
    {
        if (filled($user->attr_source)) {
            return;
        }

        $attribution = ($request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null)
            ?? $this->capture($request)
            ?? $this->direct();

        if (! is_array($attribution)) {
            $attribution = $this->direct();
        }

        $analytics = app(AnalyticsService::class);

        $user->forceFill([
            'attr_source' => $this->field($attribution['source'] ?? null),
            'attr_medium' => $this->field($attribution['medium'] ?? null),
            'attr_campaign' => $this->field($attribution['campaign'] ?? null),
            'attr_content' => $this->field($attribution['content'] ?? null),
            'attr_landing' => $this->url($attribution['landing'] ?? null),
            'attr_referrer' => $this->url($attribution['referrer'] ?? null),
            'attr_at' => $attribution['at'] ?? now(),
            'attr_json' => [
                'v' => $attribution['raw']['v'] ?? 1,
                'first' => $attribution['raw']['first'] ?? null,
                'last' => $attribution['raw']['last'] ?? null,
                'normalized' => Arr::except($attribution, ['raw', 'at']),
                'ga_client_id' => $analytics->clientId($request),
                'consent' => $analytics->consent($request),
                'captured_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromCookie(Request $request): ?array
    {
        $cookie = $request->cookie((string) config('analytics.attribution_cookie'));

        if (! is_string($cookie) || $cookie === '') {
            return null;
        }

        $decoded = json_decode($cookie, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(urldecode($cookie), true);
        }

        if (! is_array($decoded)) {
            return null;
        }

        $first = is_array($decoded['first'] ?? null) ? $decoded['first'] : $decoded;

        if (! $this->hasSignal($first)) {
            return null;
        }

        return $this->normalize($first, null, null, [
            'v' => $decoded['v'] ?? 1,
            'first' => $first,
            'last' => is_array($decoded['last'] ?? null) ? $decoded['last'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromAid(Request $request): ?array
    {
        $value = $request->query((string) config('analytics.attribution_param'));

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = $this->decodeBase64Json($value);

        if ($decoded === null || ! $this->hasSignal($decoded)) {
            return null;
        }

        return $this->normalize($decoded, null, null, ['aid' => $decoded]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromQuery(Request $request): ?array
    {
        $params = [];

        foreach (self::PARAM_KEYS as $key) {
            $value = $request->query($key);

            if (is_string($value) && trim($value) !== '') {
                $params[$key] = $value;
            }
        }

        if ($params !== []) {
            return $this->normalize($params, $request->fullUrl(), $request->headers->get('referer'), [
                'query' => $params,
            ]);
        }

        $referrer = $request->headers->get('referer');

        if (is_string($referrer) && $referrer !== '' && ! $this->isInternalReferrer($referrer)) {
            return $this->normalize([], $request->fullUrl(), $referrer, ['referrer' => $referrer]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?string $landing, ?string $referrer, array $raw): array
    {
        $source = $this->read($data, ['s', 'source', 'utm_source']);
        $medium = $this->read($data, ['m', 'medium', 'utm_medium']);
        $campaign = $this->read($data, ['c', 'campaign', 'utm_campaign']);
        $content = $this->read($data, ['k', 'content', 'utm_content']);
        $term = $this->read($data, ['term', 'keyword', 'utm_term', 't']);
        $gclid = $this->read($data, ['gclid']);
        $fbclid = $this->read($data, ['fbclid']);
        $msclkid = $this->read($data, ['msclkid']);

        $landingValue = $this->read($data, ['l', 'landing', 'landing_page']);
        $referrerValue = $this->read($data, ['r', 'referrer', 'ref']);

        $landing = $landingValue !== '' ? $landingValue : $landing;
        $referrer = $referrerValue !== '' ? $referrerValue : $referrer;

        [$source, $medium] = $this->resolveChannel($source, $medium, $referrer, $gclid, $fbclid);

        return [
            'source' => $source,
            'medium' => $medium,
            'campaign' => $campaign,
            'content' => $content,
            'term' => $term,
            'landing' => $landing,
            'referrer' => $referrer,
            'gclid' => $gclid,
            'fbclid' => $fbclid,
            'msclkid' => $msclkid,
            'at' => $this->timestamp($data['ts'] ?? $data['at'] ?? null),
            'raw' => $raw,
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function resolveChannel(string $source, string $medium, ?string $referrer, string $gclid, string $fbclid): array
    {
        if ($source !== '') {
            return [strtolower($source), $medium !== '' ? strtolower($medium) : 'unknown'];
        }

        if ($gclid !== '') {
            return ['google', $medium !== '' ? strtolower($medium) : 'cpc'];
        }

        if ($fbclid !== '') {
            return ['facebook', $medium !== '' ? strtolower($medium) : 'paid'];
        }

        if ($referrer !== null && $referrer !== '' && ! $this->isInternalReferrer($referrer)) {
            return $this->fromReferrer($referrer);
        }

        return ['direct', null];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fromReferrer(string $referrer): array
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));

        if ($host === '') {
            return ['direct', 'unknown'];
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        if ($host === 'google' || str_starts_with($host, 'google.')) {
            return ['google', 'organic'];
        }

        if (str_starts_with($host, 'yahoo.')) {
            return ['yahoo', 'organic'];
        }

        $organic = [
            'bing.com' => 'bing',
            'duckduckgo.com' => 'duckduckgo',
            'ecosia.org' => 'ecosia',
            'startpage.com' => 'startpage',
        ];

        foreach ($organic as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return [$name, 'organic'];
            }
        }

        if (str_starts_with($host, 'pinterest.')) {
            return ['pinterest', 'social'];
        }

        $social = [
            'facebook.com' => 'facebook',
            'instagram.com' => 'instagram',
            'linkedin.com' => 'linkedin',
            'lnkd.in' => 'linkedin',
            'twitter.com' => 'twitter',
            'x.com' => 'twitter',
            't.co' => 'twitter',
            'youtube.com' => 'youtube',
            'youtu.be' => 'youtube',
            'reddit.com' => 'reddit',
        ];

        foreach ($social as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return [$name, 'social'];
            }
        }

        return [$host, 'referral'];
    }

    private function isInternalReferrer(string $referrer): bool
    {
        $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));

        return $host === 'picstome.com' || str_ends_with($host, '.picstome.com');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function read(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim(strip_tags($value));
            }
        }

        return '';
    }

    private function timestamp(mixed $value): CarbonImmutable
    {
        if (! is_numeric($value)) {
            return CarbonImmutable::now();
        }

        $value = (int) $value;

        $date = $value > 1_000_000_000_000
            ? CarbonImmutable::createFromTimestampMs($value)
            : CarbonImmutable::createFromTimestamp($value);

        return $date->isFuture() ? CarbonImmutable::now() : $date;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasSignal(array $data): bool
    {
        foreach (array_merge(self::PARAM_KEYS, ['s', 'm', 'c', 'k', 't', 'l', 'r', 'source', 'medium', 'landing', 'referrer', 'ts']) as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                return true;
            }

            if (isset($data[$key]) && is_numeric($data[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBase64Json(string $value): ?array
    {
        $value = strtr($value, '-_', '+/');
        $value = str_pad($value, (int) ceil(strlen($value) / 4) * 4, '=');

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return null;
        }

        $json = json_decode($decoded, true);

        if (! is_array($json)) {
            $json = json_decode(mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1'), true);
        }

        return is_array($json) ? $json : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function direct(): array
    {
        return [
            'source' => 'direct',
            'medium' => null,
            'campaign' => null,
            'content' => null,
            'term' => null,
            'landing' => null,
            'referrer' => null,
            'gclid' => null,
            'fbclid' => null,
            'msclkid' => null,
            'at' => CarbonImmutable::now(),
            'raw' => [],
        ];
    }

    private function field(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(trim(strip_tags($value)), self::MAX_FIELD, '');
    }

    private function url(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), self::MAX_URL, '');
    }
}
