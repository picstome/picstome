<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * Colours, typography and logo applied to outgoing emails.
 *
 * Emails to photographers (Picstome users) carry the Picstome brand. Emails
 * a photographer's clients receive on their behalf (signed contract, paid
 * booking) carry the photographer's brand instead, the same one their public
 * gallery uses. Notifications opt in with
 * `MailBranding::forTeam($team)->applyTo($mail)`; everything else falls back
 * to the Picstome brand in the mail templates.
 *
 * Emails cannot read CSS variables, so the Tailwind colour a team picks is
 * mapped to a fixed hex shade that keeps white button text readable.
 */
final class MailBranding
{
    /**
     * Button shade per Tailwind colour a team may choose (see StylingForm).
     * Mostly the 600 shade; the light hues use 700 so white text stays legible.
     */
    private const TEAM_ACCENTS = [
        'red' => '#dc2626',
        'orange' => '#ea580c',
        'amber' => '#b45309',
        'yellow' => '#a16207',
        'lime' => '#4d7c0f',
        'green' => '#16a34a',
        'emerald' => '#059669',
        'teal' => '#0d9488',
        'cyan' => '#0891b2',
        'sky' => '#0284c7',
        'blue' => '#2563eb',
        'indigo' => '#4f46e5',
        'violet' => '#7c3aed',
        'purple' => '#9333ea',
        'fuchsia' => '#c026d3',
        'pink' => '#db2777',
        'rose' => '#e11d48',
        'zinc' => '#52525b',
    ];

    /** Picstome brand green, also the fallback for teams without a colour. */
    private const PICSTOME_ACCENT = '#316d61';

    private const PICSTOME_FONT = 'Inter';

    private const FALLBACK_FONT_STACK = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly ?string $logoUrl,
        public readonly int $logoWidth,
        public readonly string $accent,
        public readonly string $font,
        public readonly ?string $fontUrl,
        public readonly string $background,
        public readonly string $text,
        public readonly string $heading,
        public readonly bool $poweredByPicstome = false,
    ) {}

    public static function picstome(): self
    {
        return new self(
            name: config('app.name'),
            url: config('app.url'),
            logoUrl: config('picstome.mail_logo_url') ?: asset('app-logo.png'),
            logoWidth: 180,
            accent: self::PICSTOME_ACCENT,
            font: self::PICSTOME_FONT,
            fontUrl: 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
            background: '#fbfcf9',
            text: '#322f30',
            heading: '#2a2625',
        );
    }

    /**
     * The photographer's brand. Without a logo the header shows the team name
     * in the brand colour; without a colour or font the Picstome ones apply.
     * Like the public gallery, teams without a subscription also get a
     * "Powered by Picstome" line in the footer.
     */
    public static function forTeam(Team $team): self
    {
        $font = filled($team->brand_font) ? $team->brand_font : null;

        // The plain storage URL (PNG/JPG as uploaded): the CDN variant used on
        // the web is WebP, which Outlook cannot show.
        $logoUrl = $team->brand_logo_path
            ? Storage::disk(config('picstome.disk'))->url($team->brand_logo_path)
            : null;

        return new self(
            name: $team->name,
            url: $team->handle ? route('handle.show', ['handle' => $team->handle]) : config('app.url'),
            logoUrl: $logoUrl,
            logoWidth: 0,
            accent: self::TEAM_ACCENTS[$team->brand_color] ?? self::PICSTOME_ACCENT,
            font: $font ?? self::PICSTOME_FONT,
            fontUrl: 'https://fonts.googleapis.com/css2?family='.str_replace(' ', '+', $font ?? self::PICSTOME_FONT).':wght@400;600;700&display=swap',
            background: '#fafafa',
            text: '#3f3f46',
            heading: '#18181b',
            poweredByPicstome: ! $team->subscribed(),
        );
    }

    /**
     * Font stack for CSS: the brand font first, then system fonts for clients
     * (Gmail, Outlook) that do not load web fonts.
     */
    public function fontStack(): string
    {
        return "'{$this->font}', ".self::FALLBACK_FONT_STACK;
    }

    /**
     * Brand a notification email. The mail templates and the theme read the
     * brand from the message's view data as `$mailBrand`.
     */
    public function applyTo(MailMessage $mail): MailMessage
    {
        $mail->viewData = [...$mail->viewData, 'mailBrand' => $this];

        return $mail;
    }
}
