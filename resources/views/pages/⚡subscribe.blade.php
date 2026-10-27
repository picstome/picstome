<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Stripe\CustomerSession;
use Stripe\Stripe;

new class extends Component
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
}; ?>

<div class="h-full flex flex-col items-center justify-center">
    <div class="w-full">
        <!-- Stripe Pricing Table -->
        <stripe-pricing-table
            pricing-table-id="{{ $this->pricingTableId }}"
            publishable-key="{{ config('cashier.key') }}"
            customer-session-client-secret="{{ $this->customerSession->client_secret }}"
        >
        </stripe-pricing-table>

        <x-ts-separator variant="subtle" :text="__('Features')" />

        <div class="flex justify-center mt-8">
            <ul class="space-y-2">
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong"><span class="font-semibold">{{ __('Extra storage') }}</span> {{ __('for photos or videos') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong">{{ __('Professional human support by email') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong" class="font-semibold">{{ __('Accepts payments / POS') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong">{{ __('Galleries expire when you want') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong">{{ __('Password protect galleries') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong">{{ __('Contratos ilimitados') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong">{{ __('Comments on photos') }}</x-ts-text></li>
                <li class="flex gap-2 items-center"><x-icon name="check" variant="mini" class="text-[#316d61]" /><x-ts-text variant="strong">{{ __('White label') }}</x-ts-text></li>
            </ul>
        </div>
    </div>
</div>

@assets
    <script async src="https://js.stripe.com/v3/pricing-table.js"></script>
@endassets
