<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AppearanceSettingsForm extends Form
{
    public User $user;

    #[Validate(['required'])]
    public $language = '';

    public function setUser(User $user): void
    {
        $this->user = $user;

        $this->language = $user->language ?? config('app.locale');
    }

    public function update(): void
    {
        $this->validate();

        $this->user->update([
            'language' => $this->language,
        ]);
    }
}
