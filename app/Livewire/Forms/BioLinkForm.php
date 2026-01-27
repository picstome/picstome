<?php

namespace App\Livewire\Forms;

use App\Models\BioLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class BioLinkForm extends Form
{
    public ?BioLink $bioLink = null;

    public ?string $title = null;

    public ?string $url = null;

    protected function rules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
        ];
    }

    public function setBioLink(BioLink $bioLink)
    {
        $this->bioLink = $bioLink;

        $this->title = $bioLink->title;
        $this->url = $bioLink->url;
    }

    public function store()
    {
        $this->validate();

        return Auth::user()->currentTeam->bioLinks()->create([
            'title' => $this->title,
            'url' => $this->url,
        ]);
    }

    public function update()
    {
        $this->validate();

        return $this->bioLink->update([
            'title' => $this->title,
            'url' => $this->url,
        ]);
    }

    public function resetForm()
    {
        $this->reset(['title', 'url', 'bioLink']);
    }
}
