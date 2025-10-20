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

    #[Url]
    public ?bool $booking = null;

    #[Url]
    public ?string $booking_date = null;

    #[Url]
    public ?string $booking_start_time = null;

    #[Url]
    public ?string $booking_end_time = null;

    public ?string $formattedAmount = null;

    public int $amount;

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
        $rules = [
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'photoshoot_id' => [
                'nullable',
                'integer',
                'exists:photoshoots,id,team_id,'.$this->team->id,
            ],
            'booking' => ['nullable', 'boolean'],
            'booking_date' => ['nullable', 'required_if:booking,true', 'date'],
            'booking_start_time' => ['nullable', 'required_if:booking,true', 'date_format:H:i'],
            'booking_end_time' => ['nullable', 'required_if:booking,true', 'date_format:H:i'],
        ];

        $this->validate($rules);

        $checkoutUrl = StripeConnectService::createCheckoutSession(
            $this->team,
            route('handle.pay.success', ['handle' => $this->team->handle]).'?session_id={CHECKOUT_SESSION_ID}',
            route('handle.pay.cancel', ['handle' => $this->team->handle]),
            $this->amount * 100,
            $this->description,
            [
                'photoshoot_id' => $this->photoshoot_id,
                'booking' => $this->booking,
                'booking_date' => $this->booking_date,
                'booking_start_time' => $this->booking_start_time,
                'booking_end_time' => $this->booking_end_time,
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
