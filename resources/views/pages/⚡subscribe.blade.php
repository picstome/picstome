<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Stripe\CustomerSession;
use Stripe\Stripe;

new class extends Component
{
    public ?string $customerSessionClientSecret = null;

    public ?string $pricingTableId = null;

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

        $customerSession = CustomerSession::create([
            'customer' => $stripeId,
            'components' => [
                'pricing_table' => ['enabled' => true],
            ],
        ]);

        $this->customerSessionClientSecret = $customerSession->client_secret;

        $this->pricingTableId = app()->getLocale() === 'es'
            ? config('services.stripe.es_pricing_table_id')
            : config('services.stripe.en_pricing_table_id');
    }
}; ?>

@php
    // Pro does not unlock features the free plan lacks: every feature is
    // available on free. It only raises the limits, removes the expiration
    // date and removes the Picstome branding, so the copy must not promise
    // gated functionality.
    //
    // Each row carries the "from → to" numbers instead of repeating them in a
    // separate summary: the old pills duplicated this list and overflowed the
    // narrow hero column.
    $planBenefits = [
        [
            'icon' => 'cloud-arrow-up',
            'title' => __('Up to 1 TB storage'),
            'description' => __('1000× more space than the free plan.'),
            'from' => '1 GB',
            'to' => '1 TB',
        ],
        [
            'icon' => 'clock',
            'title' => __('Galleries that never expire'),
            'description' => __('Keep every gallery, and remove its expiration date.'),
            'from' => __('1 year'),
            'to' => __('Never'),
        ],
        [
            'icon' => 'document-text',
            'title' => __('No monthly limits'),
            'description' => __('Unlimited invoices, contracts and payments.'),
            'from' => '1',
            'to' => __('Unlimited'),
        ],
        [
            'icon' => 'paint-brush',
            'title' => __('White label'),
            'description' => __('No Picstome branding on galleries and documents.'),
            'from' => 'Picstome',
            'to' => __('Your brand'),
        ],
    ];
@endphp

<div class="flex flex-col gap-4 lg:-mx-6 lg:h-[calc(100dvh-4rem)] lg:min-h-0 lg:overflow-hidden">
    <div class="grid shrink-0 gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.25fr)]">
        <header class="border-brand-green/10 relative flex flex-col justify-between overflow-hidden rounded-3xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            <div
                class="bg-brand-sage/30 dark:bg-brand-green/20 pointer-events-none absolute -right-16 -bottom-20 size-52 rounded-full blur-3xl"
            ></div>

            <div class="relative">
                <span
                    class="bg-brand-coral/10 text-brand-coral-deep dark:bg-brand-coral/15 dark:text-brand-coral inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
                >
                    <x-icon name="sparkles" class="size-3.5" />
                    {{ __('Paid plan') }}
                </span>

                <h1 class="text-brand-green dark:text-brand-cream mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ __('Take your Picstome to the next level') }}
                </h1>

                <p class="text-brand-ink/70 dark:text-brand-sage/75 mt-2 text-sm font-medium">
                    {{ __('The free plan includes 1 GB of storage, 1 invoice, 1 contract and 1 payment per month. With Pro you get more space, galleries that never expire, no monthly limits and white label branding.') }}
                </p>
            </div>

        </header>

        <section
            class="border-brand-green/10 flex flex-col rounded-3xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-brand-ink dark:text-brand-cream text-base font-semibold">
                    {{ __('What changes with Pro') }}
                </h2>

                <x-ts-badge color="green" size="sm" variant="pill">{{ __('All included') }}</x-ts-badge>
            </div>

            <ul class="mt-3 grid flex-1 grid-cols-1 content-start gap-2 sm:grid-cols-2">
                @foreach ($planBenefits as $benefit)
                    <li
                        class="border-brand-green/10 bg-brand-paper/80 dark:border-white/10 dark:bg-white/5 flex flex-col gap-2 rounded-2xl border p-3"
                    >
                        <span class="flex items-start gap-2.5">
                            <span
                                class="text-brand-green dark:text-brand-sage bg-brand-sage-soft/70 dark:bg-brand-green/15 flex size-8 shrink-0 items-center justify-center rounded-lg"
                            >
                                <x-icon :name="$benefit['icon']" class="size-4" />
                            </span>

                            <span class="min-w-0">
                                <span class="text-brand-ink dark:text-brand-cream block text-sm leading-tight font-semibold">
                                    {{ $benefit['title'] }}
                                </span>

                                <span class="text-brand-ink/60 dark:text-brand-sage/65 mt-0.5 block text-xs leading-snug">
                                    {{ $benefit['description'] }}
                                </span>
                            </span>
                        </span>

                        <span
                            class="bg-brand-sage-soft/70 dark:bg-brand-green/15 mt-auto flex flex-wrap items-center gap-x-1.5 gap-y-0.5 rounded-lg px-2 py-1 text-xs font-semibold"
                        >
                            <span class="text-brand-ink/40 dark:text-brand-sage/45 font-medium">{{ $benefit['from'] }}</span>
                            <span aria-hidden="true" class="text-brand-green/40 dark:text-brand-sage/40">&rarr;</span>
                            <span class="text-brand-green dark:text-brand-sage">{{ $benefit['to'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <section
        class="border-brand-green/15 from-brand-sage-soft/80 flex shrink-0 flex-col rounded-3xl border bg-gradient-to-b to-white p-3 shadow-xs sm:p-4 dark:border-white/10 dark:from-brand-green/15 dark:to-brand-night-deep"
    >
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
            <h2 class="text-brand-ink dark:text-brand-cream text-base font-semibold">
                {{ __('Choose your plan') }}
            </h2>

            <ul class="text-brand-ink/55 dark:text-brand-sage/65 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-medium">
                <li class="flex items-center gap-1.5">
                    <x-icon name="shield-check" class="text-brand-green dark:text-brand-sage size-3.5" />
                    {{ __('Secure payment with Stripe') }}
                </li>
                <li class="flex items-center gap-1.5">
                    <x-icon name="arrow-path" class="text-brand-green dark:text-brand-sage size-3.5" />
                    {{ __('Cancel anytime') }}
                </li>
                <li class="flex items-center gap-1.5">
                    <x-icon name="document-text" class="text-brand-green dark:text-brand-sage size-3.5" />
                    {{ __('Subscription invoices') }}
                </li>
            </ul>
        </div>

        <div class="mt-2">
            <stripe-pricing-table
                pricing-table-id="{{ $this->pricingTableId }}"
                publishable-key="{{ config('cashier.key') }}"
                customer-session-client-secret="{{ $this->customerSessionClientSecret }}"
            ></stripe-pricing-table>
        </div>
    </section>
</div>

@assets
    <script async src="https://js.stripe.com/v3/pricing-table.js"></script>
@endassets
