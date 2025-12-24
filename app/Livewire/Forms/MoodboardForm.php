<?php

namespace App\Livewire\Forms;

use App\Models\Moodboard;
use App\Models\Photoshoot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class MoodboardForm extends Form
{
    public ?Photoshoot $photoshoot = null;

    public ?Moodboard $moodboard = null;

    public ?string $name = null;

    public ?string $description = null;

    public ?int $photoshoot_id = null;

    public bool $isPublic = false;

    protected function rules()
    {
        $team = Auth::user()?->currentTeam;

        return [
            'photoshoot_id' => [
                'nullable',
                Rule::exists('photoshoots', 'id')->where(fn ($query) => $query->where('team_id', $team->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'isPublic' => ['required', 'boolean'],
        ];
    }

    public function setPhotoshoot(Photoshoot $photoshoot)
    {
        $this->photoshoot = $photoshoot;
    }

    public function setMoodboard(Moodboard $moodboard)
    {
        $this->moodboard = $moodboard;

        $this->name = $moodboard->name;
        $this->description = $moodboard->description;
        $this->photoshoot_id = $moodboard->photoshoot_id;
        $this->isPublic = $moodboard->is_public;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->moodboards()->create([
            'photoshoot_id' => $this->photoshoot?->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => $this->isPublic,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->moodboard->update([
            'photoshoot_id' => $this->photoshoot_id ?: null,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => $this->isPublic,
        ]);
    }
}
