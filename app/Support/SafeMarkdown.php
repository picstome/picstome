<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Renders user-authored Markdown as safe HTML.
 *
 * Contract bodies, contract templates and customer notes are written by team
 * members but rendered in other people's browsers (other members, and even
 * unauthenticated clients on the public signing page). Raw HTML is stripped
 * and unsafe link schemes (e.g. `javascript:`) are rejected so a stored body
 * cannot execute script in a victim's session.
 */
final class SafeMarkdown
{
    /**
     * Options shared by every Markdown render in the application.
     *
     * @return array{html_input: string, allow_unsafe_links: bool}
     */
    public static function options(): array
    {
        return [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];
    }

    /**
     * Render Markdown to HTML with unsafe input removed.
     */
    public static function render(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', self::options());
    }
}
