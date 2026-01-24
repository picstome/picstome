<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Stripe\CustomerSession;
use Stripe\Stripe;

new #[Layout('layouts.app')] class extends Component
{
    public $customerSessionClientSecret;

    public $pricingTableId;

    public function mount()
    {
        Stripe::setApiKey(config('cashier.secret'));

        $user = Auth::user();

        if ($user->currentTeam->subscribed()) {
            return redirect()->route('dashboard');
        }

        $stripeId = $user->currentTeam->stripe_id;

        if (! $stripeId) {
            $user->currentTeam->createAsStripeCustomer();
            $stripeId = $user->currentTeam->stripe_id;
        }

        $this->customerSession = CustomerSession::create([
            'customer' => $stripeId,
            'components' => [
                'pricing_table' => ['enabled' => true],
            ],
        ]);

        if (app()->getLocale() === 'es') {
            $this->pricingTableId = config('services.stripe.es_pricing_table_id');
        } else {
            $this->pricingTableId = config('services.stripe.en_pricing_table_id');
        }
    }

    public function purchaseLifetime()
    {
        $user = Auth::user();
        $priceId = config('services.stripe.lifetime_price_id');

        return $user->currentTeam->checkout($priceId, [
            'success_url' => route('product-checkout-success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('subscribe'),
        ]);
    }
}; ?>

<div class="h-full flex flex-col items-center justify-center">
    <div class="w-full">
        <!-- Stripe Pricing Table -->
        <script async src="https://js.stripe.com/v3/pricing-table.js"></script>
        <stripe-pricing-table
            pricing-table-id="{{ $this->pricingTableId }}"
            publishable-key="{{ config('cashier.key') }}"
            customer-session-client-secret="{{ $this->customerSession->client_secret }}"
        >
        </stripe-pricing-table>

        @if (config('services.stripe.lifetime_price_id'))
            <flux:separator variant="subtle" text="or" />

            <div class="flex justify-center mt-8">
                <flux:button wire:click="purchaseLifetime" variant="filled">
                    {{ __('Purchase Lifetime Subscription') }}
                </flux:button>
            </div>
        @endif
    </div>
</div>
