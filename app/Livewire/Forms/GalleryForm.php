<?php

namespace App\Livewire\Forms;

use App\Models\Gallery;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class GalleryForm extends Form
{
    public ?Photoshoot $photoshoot = null;

    public ?Gallery $gallery;

    public ?string $name = null;

    public $expirationDate = null;

    public $keepOriginalSize = false;

    public ?int $photoshoot_id = null;

    protected function rules()
    {
        $team = Auth::user()?->currentTeam;

        return [
            'photoshoot_id' => [
                'nullable',
                Rule::exists('photoshoots', 'id')->where(fn($query) => $query->where('team_id', $team->id)),
            ],
            'name' => ['nullable', 'string'],
            'expirationDate' => $this->getExpirationRules($team),
            'keepOriginalSize' => ['required', 'boolean'],
        ];
    }

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;
    }

    public function setGallery(Gallery $gallery)
    {
        $this->gallery = $gallery;

        $this->name = $gallery->name;
        $this->expirationDate = $gallery->expiration_date?->format('Y-m-d');
        $this->photoshoot_id = $gallery->photoshoot_id;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->galleries()->create([
            'photoshoot_id' => $this->photoshoot?->id,
            'name' => $this->name ?? __('Untitled'),
            'keep_original_size' => $this->keepOriginalSize,
            'expiration_date' => $this->expirationDate ?: null,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->gallery->update([
            'photoshoot_id' => $this->photoshoot_id ?: null,
            'name' => $this->name ?? __('Untitled'),
            'expiration_date' => $this->gallery->is_public ? null : ($this->expirationDate ?: null),
        ]);
    }

    private function getExpirationRules($team)
    {
        $maxExpirationDate = now()->addYear()->format('Y-m-d');

        if ($this->gallery && $this->gallery->is_public) {
            return [
                'nullable',
                'date',
                'after_or_equal:today',
                'before_or_equal:' . $maxExpirationDate,
            ];
        }

        $rules = [
            'date',
            'after_or_equal:today',
            'before_or_equal:' . $maxExpirationDate,
        ];

        if (!$team?->subscribed()) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }
}
