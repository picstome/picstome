<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount()
    {
        return redirect()->route('branding.general');
    }
};
