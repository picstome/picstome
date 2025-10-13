<?php

use App\Models\Team;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    #[Url]
    public ?int $photoshoot_id = null;

    public ?string $formattedAmount = null;

    #[Validate('required', 'integer', 'min:1')]
    public int $amount;

    #[Validate('required', 'string', 'max:255')]
    public string $description;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        abort_unless($this->team->hasCompletedOnboarding(), 404);

        $this->formattedAmount = Cashier::formatAmount($this->amount * 100, $this->team->stripe_currency);
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name.' - Pay');
    }

    public function checkout()
    {
        $this->validate();

        if ($this->photoshoot_id) {
            abort_if(!$this->team->photoshoots()->where('id', $this->photoshoot_id)->exists(), 403);
        }

        $checkoutUrl = StripeConnectService::createCheckoutSession(
            $this->team,
            route('handle.pay.success', ['handle' => $this->team->handle]).'?session_id={CHECKOUT_SESSION_ID}',
            route('handle.pay.cancel', ['handle' => $this->team->handle]),
            $this->amount * 100,
            $this->description,
            [
                'photoshoot_id' => $this->photoshoot_id,
            ]
        );

        return redirect()->away($checkoutUrl);
    }
}; ?>

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
                <flux:text class="font-medium mb-2">{{ $description }}</flux:text>
                <flux:heading size="xl" class="font-semibold">{{ $formattedAmount }}</flux:heading>
            </div>

            <flux:button wire:click="checkout" variant="primary" icon:trailing="arrow-right">{{ __('Proceed to checkout') }}</flux:button>
        </div>
    </div>
</div>
