<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Form;

class GeneralForm extends Form
{
    public Team $team;

    public $name;
    public $handle;

    public function rules()
    {
        return [
            'name' => ['required'],
            'handle' => [
                'required',
                'string',
                'lowercase',
                'min:2',
                'max:50',
                'regex:/^[a-z0-9]+$/',
                'unique:teams,handle,' . ($this->team->id ?? 'null'),
            ],
        ];
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->name = $team->name;
        $this->handle = $team->handle;
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'name' => $this->name,
            'handle' => $this->handle,
        ]);
    }
}