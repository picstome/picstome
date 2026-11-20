<?php

namespace App\Support;

/**
 * Guards redirect targets that come from the request or from transport links.
 *
 * Only local paths are accepted: they must start with a single slash, which
 * rules out absolute URLs (open redirects) and protocol-relative URLs.
 *
 * Browsers also normalise backslashes to slashes when they resolve a Location
 * header, so `/\evil.com` becomes `//evil.com` and leaves the site. The
 * decoded value is checked as well, so encoded variants such as
 * `/%5Cevil.com` or `/%2F%2Fevil.com` cannot smuggle a backslash or a second
 * slash past the guard.
 */
class SafeRedirect
{
    public static function sanitize(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        $decoded = rawurldecode($url);

        // Header injection and control characters have no place in a path.
        if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
            return null;
        }

        // `/\x`, `\\x` and every backslash variant are external redirects.
        if (str_contains($decoded, '\\')) {
            return null;
        }

        // A decoded `//` is a protocol-relative URL even when the raw value
        // only encoded the first slash.
        if (str_starts_with($decoded, '//')) {
            return null;
        }

        return $url;
    }
}
