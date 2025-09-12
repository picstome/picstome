<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Stripe\CustomerSession;
use Stripe\Stripe;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

middleware(['auth', 'verified']);

name('subscribe');

new class extends Component
{
    public $customerSessionClientSecret;

    public $pricingTableId;

    public function mount()
    {
        Stripe::setApiKey(config('cashier.secret'));

        $user = Auth::user();

        if($user->currentTeam->subscribed()) {
            return redirect()->route('galleries');
        };

        $stripeId = $user->currentTeam->stripe_id;

        if (!$stripeId) {
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

<x-app-layout>
    @volt('pages.subscribe')
        <div class="h-full flex flex-col items-center justify-center space-y-8">
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

                <flux:button wire:click="purchaseLifetime" variant="filled">
                    {{ __('Purchase Lifetime Subscription') }}
                </flux:button>
            @endif
        </div>
    @endvolt
</x-app-layout>
