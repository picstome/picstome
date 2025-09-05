<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Form;

class PublicProfileForm extends Form
{
    public Team $team;

    public $bio;

    public $instagram;
    public $youtube;
    public $facebook;
    public $x;
    public $tiktok;
    public $twitch;
    public $website;
    public $other = ['label' => '', 'url' => ''];

    public function rules()
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'x' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'twitch' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
            'other.label' => ['nullable', 'string'],
            'other.url' => ['nullable', 'url'],
        ];
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->bio = $team->bio;
        $this->instagram = $this->extractHandle($team->instagram_url, 'instagram.com');
        $this->youtube = $this->extractHandle($team->youtube_url, 'youtube.com');
        $this->facebook = $this->extractHandle($team->facebook_url, 'facebook.com');
        $this->x = $this->extractHandle($team->x_url, 'x.com');
        $this->tiktok = $this->extractHandle($team->tiktok_url, 'tiktok.com', true);
        $this->twitch = $this->extractHandle($team->twitch_url, 'twitch.tv');
        $this->website = $team->website_url;
        $this->other = $team->other_social_links ?? ['label' => '', 'url' => ''];
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'bio' => $this->bio,
            'instagram_url' => $this->instagram ? $this->buildUrl('https://instagram.com', $this->instagram) : null,
            'youtube_url' => $this->youtube ? $this->buildUrl('https://youtube.com', $this->youtube) : null,
            'facebook_url' => $this->facebook ? $this->buildUrl('https://facebook.com', $this->facebook) : null,
            'x_url' => $this->x ? $this->buildUrl('https://x.com', $this->x) : null,
            'tiktok_url' => $this->tiktok ? $this->buildUrl('https://tiktok.com', '@'.$this->tiktok) : null,
            'twitch_url' => $this->twitch ? $this->buildUrl('https://twitch.tv', $this->twitch) : null,
            'website_url' => $this->website,
            'other_social_links' => $this->other['label'] || $this->other['url'] ? $this->other : null,
        ]);
    }

    private function extractHandle(?string $url, string $domain, bool $hasAt = false): ?string
    {
        if (!$url) {
            return null;
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host']) || !str_contains($parsed['host'], $domain)) {
            return $url; // Return as-is if not matching domain
        }

        $path = $parsed['path'] ?? '';
        $handle = ltrim($path, '/');

        if ($hasAt && str_starts_with($handle, '@')) {
            $handle = substr($handle, 1);
        }

        return $handle ?: null;
    }

    private function buildUrl(string $baseUrl, string $handle): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($handle, '/');
    }
}