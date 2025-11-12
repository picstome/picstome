<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LogosForm extends Form
{
    public Team $team;

    #[Validate(['nullable', 'image'])]
    public $logo;

    #[Validate(['nullable', 'image'])]
    public $logoIcon;

    public function setTeam(Team $team)
    {
        $this->team = $team;
    }

    public function update()
    {
        $this->validate();

        if ($this->logo) {
            $this->team->updateBrandLogo($this->logo);
        }

        if ($this->logoIcon) {
            $this->team->updateBrandLogoIcon($this->logoIcon);
        }
    }
}