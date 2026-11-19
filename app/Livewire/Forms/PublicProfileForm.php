<?php

namespace App\Livewire\Forms;

use App\Livewire\Forms\Concerns\ManagesSocialLinks;
use App\Models\Team;
use Livewire\Form;

class PublicProfileForm extends Form
{
    use ManagesSocialLinks;

    public Team $team;

    public $handle;

    public $bio;

    public function rules(): array
    {
        return [
            'handle' => [
                'required',
                'string',
                'lowercase',
                'min:2',
                'max:50',
                'regex:/^[a-z0-9]+$/',
                'unique:teams,handle,'.($this->team->id ?? 'null'),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            ...$this->socialRules(),
        ];
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;

        $this->handle = $team->handle;
        $this->bio = $team->bio;
        $this->setSocialLinks($team);
    }

    public function update(): void
    {
        $this->validate();

        $this->team->update([
            'handle' => $this->handle,
            'bio' => $this->bio,
            ...$this->socialAttributes(),
        ]);
    }
}
