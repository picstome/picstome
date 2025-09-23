<?php

use App\Models\Team;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name . ' - Payment Successful');
    }
} ?>

<div class="flex min-h-screen items-center justify-center px-4">
    <div class="mx-auto w-full max-w-md text-center">
        <div class="space-y-4">
            <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block space-y-4" wire:navigate>
                @if($team->brand_logo_icon_url)
                    <img src="{{ $team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $team->name }}" />
                @else
                    <flux:heading size="xl">{{ $team->name }}</flux:heading>
                @endif
            </a>

            <div>
                <flux:heading size="xl">Payment Successful!</flux:heading>
                <flux:text class="mt-2">Thank you for your payment. Your transaction was completed successfully.</flux:text>
            </div>
        </div>
    </div>
</div>
