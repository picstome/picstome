<?php

use App\Models\Team;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
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
        $view->title($this->team->name . ' - Payment Cancelled');
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
<flux:heading size="xl">{{ __('Payment Cancelled') }}</flux:heading>
                 <flux:text class="mt-2">{{ __('Your payment was cancelled. No charges were made.') }}</flux:text>
            </div>
        </div>
    </div>
</div>
