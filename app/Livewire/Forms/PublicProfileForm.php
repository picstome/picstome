<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Form;

class PublicProfileForm extends Form
{
    public Team $team;

    public $bio;

    public function rules()
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->bio = $team->bio;
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'bio' => $this->bio,
        ]);
    }
}