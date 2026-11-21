<?php

namespace App\Livewire\Forms;

use App\Livewire\Forms\Concerns\ManagesSocialLinks;
use App\Models\Team;
use Livewire\Form;

class SocialLinksForm extends Form
{
    use ManagesSocialLinks;

    public Team $team;

    public function rules(): array
    {
        return $this->socialRules();
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;
        $this->setSocialLinks($team);
    }

    public function update(): void
    {
        $this->validate();

        $this->team->update($this->socialAttributes());
    }
}
