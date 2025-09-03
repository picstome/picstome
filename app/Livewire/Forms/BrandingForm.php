<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Livewire\Attributes\Validate;
use Livewire\Form;

class BrandingForm extends Form
{
    public Team $team;

    #[Validate(['required'])]
    public $name;

    #[Validate(['nullable', 'image'])]
    public $logo;

    #[Validate(['nullable', 'image'])]
    public $logoIcon;

    #[Validate(['nullable', 'image'])]
    public $watermark;

    #[Validate(['in:top,bottom,middle'])]
    public $watermarkPosition;

    #[Validate(['nullable', 'in:red,orange,amber,yellow,lime,green,emerald,teal,cyan,sky,blue,indigo,violet,purple,fuchsia,pink,rose'])]
    public $color;

    #[Validate(['nullable', 'integer', 'min:0', 'max:100'])]
    public $watermarkTransparency;

    #[Validate(['nullable', 'in:Roboto Flex,Raleway,Montserrat,Work Sans,Source Sans 3,Nunito Sans,Source Serif 4,Roboto Serif,Playfair Display'])]
    public $font;

    public $handle;

    public function rules()
    {
        return [
            'handle' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z0-9]+$/',
                'unique:teams,handle,' . ($this->team->id ?? 'null'),
            ],
        ];
    }

    public function setTeam(Team $team)
    {
        $this->team = $team;

        $this->name = $team->name;
        $this->handle = $team->handle;
        $this->watermarkPosition = $team->brand_watermark_position;
        $this->watermarkTransparency = $team->brand_watermark_transparency;
        $this->color = $team->brand_color;
        $this->font = $team->brand_font;
    }

    public function update()
    {
        // Validate all fields
        $this->validate();

        $this->team->update([
            'name' => $this->name,
            'handle' => strtolower($this->handle),
            'brand_watermark_position' => $this->watermarkPosition,
            'brand_watermark_transparency' => $this->watermarkTransparency ?: null,
            'brand_color' => $this->color,
            'brand_font' => $this->font,
        ]);

        if ($this->logo) {
            $this->team->updateBrandLogo($this->logo);
        }

        if ($this->watermark) {
            $this->team->updateBrandWatermark($this->watermark);
        }

        if ($this->logoIcon) {
            $this->team->updateBrandLogoIcon($this->logoIcon);
        }
    }
}
