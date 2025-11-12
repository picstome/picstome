<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Attributes\Validate;
use Livewire\Form;

class WatermarkForm extends Form
{
    public Team $team;

    #[Validate(['nullable', 'image'])]
    public $watermark;

    #[Validate(['in:top,bottom,middle,repeated'])]
    public $watermarkPosition;

    #[Validate(['nullable', 'integer', 'min:0', 'max:100'])]
    public $watermarkTransparency;

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->watermarkPosition = $team->brand_watermark_position;
        $this->watermarkTransparency = $team->brand_watermark_transparency;
    }

    public function update()
    {
        $this->validate();

        $this->team->update([
            'brand_watermark_position' => $this->watermarkPosition,
            'brand_watermark_transparency' => $this->watermarkTransparency ?: null,
        ]);

        if ($this->watermark) {
            $this->team->updateBrandWatermark($this->watermark);
        }
    }
}
