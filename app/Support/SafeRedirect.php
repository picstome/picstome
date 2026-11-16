<?php

namespace App\Support;

/**
 * Guards redirect targets that come from the request or from transport links.
 *
 * Only local paths are accepted: they must start with a single slash, which
 * rules out absolute URLs (open redirects) and protocol-relative URLs.
 */
class SafeRedirect
{
    public static function sanitize(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? $url
            : null;
    }
}
