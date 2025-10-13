<?php

use App\Models\Payment;
use App\Models\Team;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public array $checkoutSession = [];

    #[Url]
    public ?string $session_id = null;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        if ($this->session_id) {
            $this->checkoutSession = StripeConnectService::getCheckoutSession($this->session_id, $this->team->stripe_account_id);

            if (($this->checkoutSession['payment_status'] ?? null) === 'paid') {
                $paymentIntentId = $this->checkoutSession['payment_intent'] ?? null;

                if ($paymentIntentId) {
                    $existing = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

                    if (!$existing) {
                        $this->team->payments()->create([
                            'amount' => $this->checkoutSession['amount_total'] ?? 0,
                            'currency' => $this->checkoutSession['currency'] ?? 'usd',
                            'stripe_payment_intent_id' => $paymentIntentId,
                            'description' => $this->checkoutSession['line_items']['data'][0]['description'] ?? null,
                            'customer_email' => $this->checkoutSession['customer_details']['email'] ?? null,
                            'completed_at' => now(),
                        ]);
                    }
                }
            }
        }
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
                <flux:heading size="xl">{{ __('Payment Successful!') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Thank you for your payment. Your transaction was completed successfully.') }}</flux:text>
            </div>
        </div>
    </div>
</div>
