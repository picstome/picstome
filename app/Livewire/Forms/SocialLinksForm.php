<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Form;

class SocialLinksForm extends Form
{
    public Team $team;

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

        $this->instagram = $team->instagram_handle;
        $this->youtube = $team->youtube_handle;
        $this->facebook = $team->facebook_handle;
        $this->x = $team->x_handle;
        $this->tiktok = $team->tiktok_handle;
        $this->twitch = $team->twitch_handle;
        $this->website = $team->website_url;
        $this->other = $team->other_social_links ?? ['label' => '', 'url' => ''];
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'instagram_handle' => $this->instagram ?: null,
            'youtube_handle' => $this->youtube ?: null,
            'facebook_handle' => $this->facebook ?: null,
            'x_handle' => $this->x ?: null,
            'tiktok_handle' => $this->tiktok ?: null,
            'twitch_handle' => $this->twitch ?: null,
            'website_url' => $this->website,
            'other_social_links' => $this->other['label'] || $this->other['url'] ? $this->other : null,
        ]);
    }
}
