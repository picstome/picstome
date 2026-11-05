<?php

namespace App\Livewire\Forms;

use App\Models\Team;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class WatermarkForm extends Form
{
    public Team $team;

    #[Validate(['in:image,text'])]
    public string $watermarkType = 'image';

    #[Validate(['nullable', 'required_if:watermarkType,text', 'string', 'max:100'])]
    public ?string $watermarkText = null;

    public ?string $watermarkFont = null;

    public ?string $watermarkSize = null;

    #[Validate(['nullable', 'image'])]
    public $watermark;

    #[Validate(['in:top,bottom,middle,repeated'])]
    public $watermarkPosition;

    #[Validate(['nullable', 'integer', 'min:0', 'max:100'])]
    public $watermarkTransparency;

    public function rules(): array
    {
        return [
            'watermarkFont' => ['nullable', Rule::in(array_keys(config('picstome.brand_fonts')))],
            'watermarkSize' => ['nullable', Rule::in(array_keys(config('picstome.watermark_sizes')))],
        ];
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;

        $this->watermarkType = $team->brand_watermark_type;
        $this->watermarkText = $team->brand_watermark_text;
        $this->watermarkFont = $team->brand_watermark_font;
        $this->watermarkSize = $team->brand_watermark_size;
        $this->watermarkPosition = $team->brand_watermark_position;
        $this->watermarkTransparency = $team->brand_watermark_transparency;
    }

    public function update(): void
    {
        $this->validate();

        $this->team->update([
            'brand_watermark_type' => $this->watermarkType,
            'brand_watermark_text' => filled($this->watermarkText) ? $this->watermarkText : null,
            'brand_watermark_font' => filled($this->watermarkFont) ? $this->watermarkFont : null,
            'brand_watermark_size' => filled($this->watermarkSize) ? $this->watermarkSize : null,
            'brand_watermark_position' => $this->watermarkPosition,
            'brand_watermark_transparency' => $this->watermarkTransparency ?: null,
        ]);

        if ($this->watermarkType === 'image' && $this->watermark) {
            $this->team->updateBrandWatermark($this->watermark);
        }
    }
}
