<?php

use App\Models\Team;
use App\Services\FakePaymentService;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

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

    #[Url]
    public ?string $timezone = null;

    public ?string $formattedAmount = null;

    public int $amount;

    public string $description;

    public ?string $email = null;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        abort_unless($this->team->hasCompletedOnboarding(), 404);

        $this->formattedAmount = Cashier::formatAmount($this->amount * 100, $this->team->stripe_currency);

        if (FakePaymentService::enabled()) {
            return;
        }

        $this->checkout();
    }

    /**
     * Create the payment without Stripe (local fake payments mode).
     */
    public function pay()
    {
        $this->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
        ]);

        app(FakePaymentService::class)->createPayment(
            $this->team,
            $this->amount * 100,
            $this->description,
            ['email' => $this->email]
        );

        return redirect()->route('handle.pay.success', [
            'handle' => $this->team->handle,
            'fake' => 1,
        ]);
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
            'timezone' => ['nullable', 'required_if:booking,true', 'string', 'max:64'],
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
                'timezone' => $this->timezone,
            ]
        );

        return redirect()->away($checkoutUrl);
    }
}; ?>

<div class="flex min-h-screen items-center justify-center px-4">
    <div class="mx-auto w-full max-w-md space-y-6">
        @if (FakePaymentService::enabled())
            <flux:callout icon="beaker" variant="secondary">
                <flux:callout.heading>{{ __('Fake payments mode') }}</flux:callout.heading>
                <flux:callout.text>{{ __('No Stripe is involved: the payment is created directly, so you can test the whole flow.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <flux:heading size="xl">{{ $this->team->name }}</flux:heading>
        <flux:text>{{ $this->description }} · {{ $this->formattedAmount }}</flux:text>

        <form wire:submit="pay" class="space-y-6">
            <flux:input wire:model="email" type="email" :label="__('Email (optional)')" />
            <flux:button type="submit" variant="primary" class="w-full">{{ __('Pay (simulated)') }}</flux:button>
        </form>
    </div>
</div>
