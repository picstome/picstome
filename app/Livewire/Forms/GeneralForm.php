<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Form;

class GeneralForm extends Form
{
    public Team $team;

    public $name;

    public function rules()
    {
        return [
            'name' => ['required'],
        ];
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->name = $team->name;
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'name' => $this->name,
        ]);
    }
}
