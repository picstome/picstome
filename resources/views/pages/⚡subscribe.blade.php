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
    $planBenefits = [
        [
            'icon' => 'document-text',
            'title' => __('Unlimited invoices'),
            'description' => __('Issue as many as you need.'),
            'is_new' => true,
        ],
        [
            'icon' => 'clipboard-document-list',
            'title' => __('Unlimited contracts'),
            'description' => __('Sign as many as you need.'),
        ],
        [
            'icon' => 'cloud-arrow-up',
            'title' => __('Up to 1 TB storage'),
            'description' => __('Up to 1000× more space than the free plan.'),
        ],
        [
            'icon' => 'banknotes',
            'title' => __('Stripe payments'),
            'description' => __('Payment links and point of sale.'),
        ],
        [
            'icon' => 'lock-closed',
            'title' => __('Private galleries'),
            'description' => __('Passwords, comments and photo annotations.'),
        ],
        [
            'icon' => 'clock',
            'title' => __('Optional expiry'),
            'description' => __('Choose when each gallery expires.'),
        ],
        [
            'icon' => 'camera',
            'title' => __('Camera upload'),
            'description' => __('Shoot and upload over FTP/SFTP. Keep the original size.'),
        ],
        [
            'icon' => 'paint-brush',
            'title' => __('White label'),
            'description' => __('No Picstome branding.'),
        ],
        [
            'icon' => 'user-group',
            'title' => __('Human support'),
            'description' => __('Real people by email.'),
        ],
    ];
@endphp

<div class="flex flex-col gap-4 lg:-mx-6 lg:h-[calc(100dvh-4rem)] lg:min-h-0 lg:overflow-hidden">
    <div class="grid shrink-0 gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.25fr)]">
        <header class="border-brand-green/10 relative flex flex-col justify-between overflow-hidden rounded-3xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
            <div
                class="bg-brand-sage/30 dark:bg-brand-green/20 pointer-events-none absolute -right-16 -bottom-20 size-52 rounded-full blur-3xl"
            ></div>
            <x-icon
                name="sparkles"
                class="text-brand-green/10 dark:text-brand-sage/10 pointer-events-none absolute right-4 bottom-3 size-24"
            />

            <div class="relative">
                <span
                    class="bg-brand-coral/10 text-brand-coral-deep dark:bg-brand-coral/15 dark:text-brand-coral inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
                >
                    <x-icon name="sparkles" class="size-3.5" />
                    {{ __('Paid plan') }}
                </span>

                <h1 class="text-brand-green dark:text-brand-cream mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ __('Your studio, without limits') }}
                </h1>

                <p class="text-brand-ink/70 dark:text-brand-sage/75 mt-2 text-sm font-medium">
                    {{ __('The free plan includes 1 invoice, 1 contract and 1 GB of storage. With Pro you invoice, contract and charge without limits.') }}
                </p>
            </div>

            <dl class="border-brand-green/10 relative mt-4 grid grid-cols-3 gap-2 border-t pt-3 dark:border-white/10">
                <div class="bg-brand-sage-soft/70 dark:bg-brand-green/15 rounded-xl px-2.5 py-2">
                    <dt class="text-brand-green/70 dark:text-brand-sage/70 text-[10px] font-semibold tracking-wide uppercase">
                        {{ __('Invoices') }}
                    </dt>
                    <dd class="text-brand-ink dark:text-brand-cream mt-0.5 text-sm font-semibold whitespace-nowrap">
                        1 → <span class="text-brand-green dark:text-brand-sage">{{ __('Unlimited') }}</span>
                    </dd>
                </div>

                <div class="bg-brand-sage-soft/70 dark:bg-brand-green/15 rounded-xl px-2.5 py-2">
                    <dt class="text-brand-green/70 dark:text-brand-sage/70 text-[10px] font-semibold tracking-wide uppercase">
                        {{ __('Contracts') }}
                    </dt>
                    <dd class="text-brand-ink dark:text-brand-cream mt-0.5 text-sm font-semibold whitespace-nowrap">
                        1 → <span class="text-brand-green dark:text-brand-sage">{{ __('Unlimited') }}</span>
                    </dd>
                </div>

                <div class="bg-brand-sage-soft/70 dark:bg-brand-green/15 rounded-xl px-2.5 py-2">
                    <dt class="text-brand-green/70 dark:text-brand-sage/70 text-[10px] font-semibold tracking-wide uppercase">
                        {{ __('Storage') }}
                    </dt>
                    <dd class="text-brand-ink dark:text-brand-cream mt-0.5 text-sm font-semibold whitespace-nowrap">
                        1 GB → <span class="text-brand-green dark:text-brand-sage">1 TB</span>
                    </dd>
                </div>
            </dl>
        </header>

        <section
            class="border-brand-green/10 flex flex-col rounded-3xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-brand-ink dark:text-brand-cream text-base font-semibold">
                    {{ __('What you unlock with Pro') }}
                </h2>

                <x-ts-badge color="green" size="sm" variant="pill">{{ __('All included') }}</x-ts-badge>
            </div>

            <ul class="mt-3 grid flex-1 grid-cols-2 content-start gap-2 sm:grid-cols-3">
                @foreach ($planBenefits as $benefit)
                    <li
                        class="border-brand-green/10 bg-brand-paper/80 dark:border-white/10 dark:bg-white/5 flex flex-col gap-1 rounded-2xl border p-2.5"
                    >
                        <span class="text-brand-ink dark:text-brand-cream flex items-start gap-2 text-sm leading-tight font-semibold">
                            <x-icon :name="$benefit['icon']" class="text-brand-green dark:text-brand-sage mt-0.5 size-4 shrink-0" />

                            <span class="min-w-0">
                                {{ $benefit['title'] }}

                                @if ($benefit['is_new'] ?? false)
                                    <span
                                        class="bg-brand-coral/10 text-brand-coral-deep dark:bg-brand-coral/15 dark:text-brand-coral ms-1 inline-flex rounded-full px-1.5 py-0.5 align-middle text-[10px] font-semibold tracking-wide uppercase"
                                    >
                                        {{ __('New') }}
                                    </span>
                                @endif
                            </span>
                        </span>

                        <p class="text-brand-ink/60 dark:text-brand-sage/65 ps-6 text-xs leading-snug [@media(max-height:800px)]:hidden">
                            {{ $benefit['description'] }}
                        </p>
                    </li>
                @endforeach
            </ul>

            <p class="text-brand-ink/50 dark:text-brand-sage/50 mt-2 flex items-center gap-1.5 text-xs font-medium">
                <x-icon name="check-circle" class="text-brand-green dark:text-brand-sage size-3.5" />
                {{ __('Everything in the free plan stays included.') }}
            </p>
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
