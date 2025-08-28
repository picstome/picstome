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

        abort_if($user->currentTeam->subscribed(), 403);

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
}; ?>

<x-app-layout>
    @volt('pages.subscribe')
        <div class="h-full flex flex-row items-center justify-center">
            <script async src="https://js.stripe.com/v3/pricing-table.js"></script>
            <stripe-pricing-table
                pricing-table-id="{{ $this->pricingTableId }}"
                publishable-key="{{ config('cashier.key') }}"
                customer-session-client-secret="{{ $this->customerSession->client_secret }}"
            >
            </stripe-pricing-table>
        </div>
    @endvolt
</x-app-layout>
