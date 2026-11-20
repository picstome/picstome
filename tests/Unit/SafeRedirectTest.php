<?php

use App\Support\SafeRedirect;

it('keeps local paths', function (?string $url) {
    expect(SafeRedirect::sanitize($url))->toBe($url);
})->with([
    'root' => ['/'],
    'path' => ['/galleries'],
    'query' => ['/galleries?foo=bar&utm_source=newsletter'],
    'campaign query' => ['/subscribe?plan=pro&ps_from=bf2026'],
    'fragment' => ['/galleries/15#photo-42'],
]);

it('rejects anything that could leave the site', function (?string $url) {
    expect(SafeRedirect::sanitize($url))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'absolute url' => ['https://evil.com'],
    'protocol relative' => ['//evil.com'],
    'triple slash' => ['///evil.com'],
    'backslash host' => ['/\evil.com'],
    'leading backslash' => ['\\evil.com'],
    'encoded backslash' => ['/%5Cevil.com'],
    'encoded double slash' => ['/%2F%2Fevil.com'],
    'raw encoded path' => ['%2Fevil.com'],
    'crlf injection' => ["/galleries\r\nSet-Cookie: x=1"],
    'javascript scheme' => ['javascript:alert(1)'],
]);
