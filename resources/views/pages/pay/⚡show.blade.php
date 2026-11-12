<?php

use App\Models\Team;
use App\Services\FakePaymentService;
use App\Services\PaymentPlanLimitService;
use Facades\App\Services\StripeConnectService;
use Illuminate\Validation\ValidationException;
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

    /**
     * The photographer has used up their monthly payment quota, so no new
     * checkout can be started. Visitors see a friendly message instead of a
     * Stripe error, and the photographer gets an upgrade path in the app.
     */
    public bool $paymentUnavailable = false;

    public int $amount;

    public string $description;

    public ?string $email = null;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        abort_unless($this->team->hasCompletedOnboarding(), 404);

        $this->formattedAmount = Cashier::formatAmount($this->amount * 100, $this->team->stripe_currency);

        if (! app(PaymentPlanLimitService::class)->canReceive($this->team)) {
            $this->paymentUnavailable = true;

            return;
        }

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
        $this->guardPaymentLimit();

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

    /**
     * Stop a payment that arrived after the photographer used up the monthly
     * quota. The visitor only sees a generic message: billing details belong
     * to the photographer, not to their client.
     */
    protected function guardPaymentLimit(): void
    {
        if (! app(PaymentPlanLimitService::class)->canReceive($this->team)) {
            $this->paymentUnavailable = true;

            throw ValidationException::withMessages([
                'amount' => __('Payments are not available at the moment. Please contact us.'),
            ]);
        }
    }

    public function checkout()
    {
        $this->guardPaymentLimit();

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
            <x-ts-callout icon="beaker" variant="secondary">
                <x-ts-callout-heading>{{ __('Fake payments mode') }}</x-ts-callout-heading>
                <x-ts-callout-text>{{ __('No Stripe is involved: the payment is created directly, so you can test the whole flow.') }}</x-ts-callout-text>
            </x-ts-callout>
        @endif

        <x-ts-heading size="xl">{{ $this->team->name }}</x-ts-heading>

        @if ($this->paymentUnavailable)
            <x-ts-callout icon="exclamation-circle" variant="warning">
                <x-ts-callout-heading>{{ __('Payment cannot be processed') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('Payment cannot be processed at the moment. Please contact us.') }}
                </x-ts-callout-text>
            </x-ts-callout>
        @else
            <x-ts-text>{{ $this->description }} · {{ $this->formattedAmount }}</x-ts-text>

            <form wire:submit="pay" class="space-y-6">
                <x-ts-input wire:model="email" type="email" :label="__('Email (optional)')" />
                <x-ts-button type="submit" variant="primary" class="w-full">{{ __('Pay (simulated)') }}</x-ts-button>
            </form>
        @endif
    </div>
</div>
