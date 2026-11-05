<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class StylingForm extends Form
{
    public Team $team;

    #[Validate(['nullable', 'in:red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose,zinc'])]
    public $color;

    public $font;

    public function rules(): array
    {
        return [
            'font' => ['nullable', Rule::in(array_keys(config('picstome.brand_fonts')))],
        ];
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;

        $this->color = $team->brand_color;
        $this->font = $team->brand_font;
    }

    public function update(): void
    {
        $this->validate();

        $this->team->update([
            'brand_color' => $this->color,
            'brand_font' => $this->font,
        ]);
    }
}
