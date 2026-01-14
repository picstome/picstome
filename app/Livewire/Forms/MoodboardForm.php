<?php

namespace App\Livewire\Forms;

use App\Models\Moodboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class MoodboardForm extends Form
{
    public ?Moodboard $moodboard;

    public ?string $title = null;

    public ?string $description = null;

    protected function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function setMoodboard(Moodboard $moodboard)
    {
        $this->moodboard = $moodboard;

        $this->title = $moodboard->title;
        $this->description = $moodboard->description;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->moodboards()->create([
            'title' => $this->title,
            'description' => $this->description,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->moodboard->update([
            'title' => $this->title,
            'description' => $this->description,
        ]);
    }
}
