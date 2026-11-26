<?php

use App\Models\Team;
use App\Services\FakePaymentService;
use App\Services\PaymentLinkService;
use App\Services\PaymentPlanLimitService;
use Carbon\CarbonImmutable;
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

    public int $amount = 0;

    public string $description = '';

    public ?string $email = null;

    public ?string $name = null;

    public ?string $phone = null;

    public bool $bizumFormVisible = false;

    public function mount(string $handle, ?int $amount = null, ?string $description = null)
    {
        if ($amount !== null) {
            $this->amount = $amount;
        }

        if ($description !== null) {
            $this->description = $description;
        }

        $this->team = Team::where('handle', $handle)->firstOrFail();

        abort_unless($this->team->hasCompletedOnboarding() || $this->team->canAcceptBizumPayments(), 404);

        $this->formattedAmount = Cashier::formatAmount($this->amount * 100, $this->team->stripe_currency ?: 'eur');

        if (! app(PaymentPlanLimitService::class)->canReceive($this->team)) {
            $this->paymentUnavailable = true;

            return;
        }

        if (FakePaymentService::enabled()) {
            return;
        }

        // When Bizum is available the visitor chooses the method first. Teams
        // without Bizum keep the original direct redirect to Stripe.
        if ($this->team->canAcceptBizumPayments()) {
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

    /**
     * Reveal the Bizum form only when the photographer configured a phone.
     */
    public function showBizumForm(): void
    {
        abort_unless($this->team->canAcceptBizumPayments(), 404);

        $this->bizumFormVisible = true;
    }

    /**
     * Create a pending Bizum payment and show the transfer instructions.
     */
    public function payWithBizum()
    {
        $this->guardPaymentLimit();

        abort_unless($this->team->canAcceptBizumPayments(), 404);

        $this->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => [$this->team->phone_required ? 'required' : 'nullable', 'string', 'max:50'],
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
        ]);

        $metadata = array_filter([
            'booking' => $this->booking ? true : null,
            'description' => $this->description,
            'booking_date' => $this->booking_date,
            'booking_start_time' => $this->booking_start_time,
            'booking_end_time' => $this->booking_end_time,
            'timezone' => $this->timezone,
        ], fn ($value) => $value !== null && $value !== '');

        $payment = app(PaymentLinkService::class)->startBizum($this->team, [
            'amount' => $this->amount * 100,
            'currency' => $this->team->stripe_currency ?: 'eur',
            'description' => $this->description,
            'customer_name' => $this->name,
            'customer_email' => $this->email,
            'customer_phone' => $this->phone,
            'photoshoot_id' => $this->photoshoot_id,
            'metadata' => $metadata,
        ]);

        return redirect()->route('handle.pay.request', [
            'handle' => $this->team->handle,
            'reference' => $payment->reference,
        ]);
    }

    public function sessionLabel(): ?string
    {
        if (! $this->booking || blank($this->booking_date)) {
            return null;
        }

        $date = CarbonImmutable::parse($this->booking_date, $this->timezone ?: config('app.timezone'));
        $time = collect([$this->booking_start_time, $this->booking_end_time])->filter()->implode(' - ');

        return $date->isoFormat('LL').($time !== '' ? ' · '.$time : '');
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
        abort_unless($this->team->hasCompletedOnboarding(), 404);

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
            ],
            $this->team->phone_required,
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
            <div class="text-center">
                <x-ts-heading size="xl" level="1">{{ $this->description }}</x-ts-heading>

                @if ($this->sessionLabel())
                    <p class="mt-1 text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ $this->sessionLabel() }}</p>
                @endif

                <p class="text-brand-ink dark:text-brand-cream mt-3 text-3xl font-black tabular-nums">{{ $this->formattedAmount }}</p>
            </div>

            @if ($this->bizumFormVisible)
                <form wire:submit="payWithBizum" class="space-y-6">
                    <x-ts-input wire:model="name" :label="__('Name')" required />
                    <x-ts-input wire:model="email" type="email" :label="__('Email')" required />
                    <x-ts-input wire:model="phone" type="tel" :label="$this->team->phone_required ? __('Phone') : __('Phone (optional)')" :required="$this->team->phone_required" />

                    <div class="flex flex-col gap-2">
                        <x-ts-button type="submit" variant="primary" class="w-full">
                            {{ __('Continue with Bizum') }}
                        </x-ts-button>
                        <x-ts-button type="button" variant="secondary" class="w-full" wire:click="$set('bizumFormVisible', false)">
                            {{ __('Back') }}
                        </x-ts-button>
                    </div>
                </form>
            @else
                @if (FakePaymentService::enabled())
                    <form wire:submit="pay" class="space-y-4">
                        <x-ts-input wire:model="email" type="email" :label="__('Email (optional)')" />

                        <div @class(['grid gap-2', 'sm:grid-cols-2' => $this->team->canAcceptBizumPayments()])>
                            @if ($this->team->canAcceptBizumPayments())
                                <x-ts-button type="button" variant="secondary" class="w-full" wire:click="showBizumForm">
                                    {{ __('Pay with Bizum') }}
                                </x-ts-button>
                            @endif

                            <x-ts-button type="submit" variant="primary" class="w-full">{{ __('Pay (simulated)') }}</x-ts-button>
                        </div>
                    </form>
                @else
                    @php
                        $hasCard = $this->team->hasCompletedOnboarding();
                        $hasBizum = $this->team->canAcceptBizumPayments();
                    @endphp

                    @if ($hasCard && $hasBizum)
                        <div class="grid gap-2 sm:grid-cols-2">
                            <x-ts-button type="button" variant="secondary" class="w-full" wire:click="showBizumForm">
                                {{ __('Pay with Bizum') }}
                            </x-ts-button>
                            <x-ts-button type="button" variant="primary" class="w-full" wire:click="checkout">
                                {{ __('Pay by card') }}
                            </x-ts-button>
                        </div>
                    @elseif ($hasBizum)
                        <x-ts-button type="button" variant="secondary" class="w-full" wire:click="showBizumForm">
                            {{ __('Pay with Bizum') }}
                        </x-ts-button>
                    @elseif ($hasCard)
                        <x-ts-button type="button" variant="primary" class="w-full" wire:click="checkout">
                            {{ __('Pay by card') }}
                        </x-ts-button>
                    @endif
                @endif
            @endif
        @endif
    </div>
</div>
