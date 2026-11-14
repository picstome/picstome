@php
    $isPublic = $public ?? false;
    $state = $this->state();
    $offer = $this->offer();
    $promoActive = $this->promoActive();
    $isGuest = $state === 'guest';
    $isFree = $state === 'free' || $isGuest;
    $isMonthly = $state === 'monthly';
    $isAnnual = $state === 'annual';
    $isLifetime = $state === 'lifetime';
    $isBilling = $state === 'billing';
    $plans = $this->plans();
    $scheduled = $this->scheduledChange();
    $currentTier = $this->currentTier();
    $showPlans = $isFree || ($isMonthly && $promoActive && $scheduled === null);
    $displayInterval = $isMonthly ? 'annual' : $this->interval;
@endphp

<div @class(['mx-auto flex w-full max-w-6xl flex-col gap-6', 'py-4 lg:py-8' => $isPublic])>
    @if ($isPublic)
        <header class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2" aria-label="Picstome">
                <img src="/app-logo.png" class="h-14 rounded dark:hidden" alt="Picstome" />
                <img src="/app-logo-dark.png" class="hidden h-14 rounded dark:block" alt="Picstome" />
            </a>

            <div class="flex items-center gap-2">
                @auth
                    @if (in_array($this->state(), ['monthly', 'annual'], true))
                        <x-ts-button :href="$this->manageBillingUrl()" variant="primary" size="sm">
                            {{ __('Manage subscription') }}
                        </x-ts-button>
                    @else
                        <x-ts-button :href="route('dashboard')" variant="primary" size="sm">
                            {{ __('Dashboard') }}
                        </x-ts-button>
                    @endif
                @else
                    <x-ts-button
                        :href="route('login', ['redirect' => route('subscribe', ['interval' => $this->interval], absolute: false)])"
                        variant="subtle"
                        size="sm"
                    >
                        {{ __('Log in') }}
                    </x-ts-button>

                    <x-ts-button :href="route('register')" variant="primary" size="sm">
                        {{ __('Start free') }}
                    </x-ts-button>
                @endauth
            </div>
        </header>
    @endif

    @if ($this->statusMessage !== '')
        <x-ts-callout
            :variant="$this->statusType === 'error' ? 'danger' : ($this->statusType === 'warning' ? 'warning' : 'success')"
            icon="information-circle"
        >
            <x-ts-callout-text>{{ $this->statusMessage }}</x-ts-callout-text>
        </x-ts-callout>
    @endif

    <header
        class="border-brand-green/10 relative overflow-hidden rounded-3xl border bg-white p-6 shadow-xs sm:p-8 dark:border-white/10 dark:bg-white/5"
    >
        <div
            class="bg-brand-sage/30 dark:bg-brand-green/20 pointer-events-none absolute -top-20 -right-16 size-56 rounded-full blur-3xl"
        ></div>

        <div class="relative max-w-3xl">
            @if ($promoActive && in_array($offer, ['free', 'monthly'], true))
                <span
                    class="bg-brand-coral/10 text-brand-coral-deep dark:bg-brand-coral/15 dark:text-brand-coral inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
                >
                    <x-icon name="sparkles" class="size-3.5" />
                    {{ __('Black Friday 2026') }}
                </span>

                <h1
                    class="text-brand-green dark:text-brand-cream mt-3 text-2xl font-semibold tracking-tight sm:text-4xl"
                >
                    @if ($offer === 'monthly')
                        {{ __('Switch to annual and save 20%') }}
                    @else
                        {{ __('Your first month from 1 €, or 20% off annual and one-time') }}
                    @endif
                </h1>

                <p class="text-brand-ink/70 dark:text-brand-sage/75 mt-3 text-sm font-medium sm:text-base">
                    @if ($offer === 'monthly')
                        {{ __('Schedule the change for your next renewal. You pay nothing today and the 20% is applied to your first annual invoice.') }}
                    @else
                        {{ __('Choose 100 GB or 250 GB for 1 € your first month, 1 TB for 5 €, or 20% off annual and one-time. No codes: we apply the coupon automatically.') }}
                    @endif
                </p>
            @else
                <span
                    class="bg-brand-green/10 text-brand-green dark:bg-brand-sage/15 dark:text-brand-sage inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide uppercase"
                >
                    <x-icon name="shield-check" class="size-3.5" />
                    {{ __('Paid plan') }}
                </span>

                <h1
                    class="text-brand-green dark:text-brand-cream mt-3 text-2xl font-semibold tracking-tight sm:text-4xl"
                >
                    {{ __('Take your Picstome to the next level') }}
                </h1>

                <p class="text-brand-ink/70 dark:text-brand-sage/75 mt-3 text-sm font-medium sm:text-base">
                    {{ __('More space, galleries that never expire, unlimited invoices, contracts and payments, and white label branding.') }}
                </p>
            @endif

            @if ($promoActive && in_array($offer, ['free', 'monthly'], true))
                <p class="text-brand-ink/50 dark:text-brand-sage/55 mt-4 text-xs font-medium">
                    {{ __('Offer valid until :date.', ['date' => $this->promoEndsLabel()]) }}
                </p>
            @endif
        </div>
    </header>

    @if ($isBilling)
        <x-ts-callout variant="warning" icon="exclamation-triangle">
            <x-ts-callout-heading>{{ __('Your subscription needs attention') }}</x-ts-callout-heading>
            <x-ts-callout-text>
                {{ __('We could not confirm the promotion for this subscription. Please review your billing details or contact support.') }}
            </x-ts-callout-text>

            <x-slot name="actions">
                <x-ts-button :href="$this->manageBillingUrl()" variant="primary">
                    {{ __('Manage subscription') }}
                </x-ts-button>
            </x-slot>
        </x-ts-callout>
    @elseif ($isAnnual || $isLifetime)
        @include('partials.pricing-current-plan', [
            'state' => $state,
            'currentTier' => $currentTier,
            'scheduled' => $scheduled,
        ])

        <x-subscription-benefits :lifetime="$isLifetime" />
    @else
        @if ($isMonthly)
            @include('partials.pricing-current-plan', [
                'state' => $state,
                'currentTier' => $currentTier,
                'scheduled' => $scheduled,
            ])
        @endif

        @if ($showPlans)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-brand-ink dark:text-brand-cream text-lg font-semibold">
                        {{ __('Choose your plan') }}
                    </h2>
                    <p class="text-brand-ink/60 dark:text-brand-sage/65 mt-1 text-sm">
                        {{ __('Monthly, annual or one-time. Every option includes every Picstome feature.') }}
                    </p>
                </div>

                @if ($isFree)
                    <div
                        class="border-brand-green/15 bg-brand-sage/20 inline-flex rounded-xl border p-1 dark:border-white/10 dark:bg-white/5"
                    >
                        <button
                            type="button"
                            wire:click="$set('interval', 'monthly')"
                            @class([
                                'cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition hover:bg-white/70 dark:hover:bg-white/10',
                                'text-brand-green dark:text-brand-cream bg-white shadow-xs dark:bg-white/10' => $this->interval === 'monthly',
                                'text-brand-ink/60 dark:text-brand-sage/65' => $this->interval !== 'monthly',
                            ])
                        >
                            {{ __('Monthly') }}
                        </button>

                        <button
                            type="button"
                            wire:click="$set('interval', 'annual')"
                            @class([
                                'cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition hover:bg-white/70 dark:hover:bg-white/10',
                                'text-brand-green dark:text-brand-cream bg-white shadow-xs dark:bg-white/10' => $this->interval === 'annual',
                                'text-brand-ink/60 dark:text-brand-sage/65' => $this->interval !== 'annual',
                            ])
                        >
                            {{ __('Annual') }}
                        </button>

                        <button
                            type="button"
                            wire:click="$set('interval', 'lifetime')"
                            @class([
                                'cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold transition hover:bg-white/70 dark:hover:bg-white/10',
                                'text-brand-green dark:text-brand-cream bg-white shadow-xs dark:bg-white/10' => $this->interval === 'lifetime',
                                'text-brand-ink/60 dark:text-brand-sage/65' => $this->interval !== 'lifetime',
                            ])
                        >
                            {{ __('One-time') }}
                        </button>
                    </div>
                @endif
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        $isLifetimeView = $displayInterval === 'lifetime';
                        $isAnnualView = $displayInterval === 'annual';
                        $price = $isLifetimeView ? $plan['lifetime'] : ($isAnnualView ? $plan['annual'] : $plan['monthly']);
                        $promoPrice = null;
                        $promoLabel = null;
                        $promoMonthlyEquivalent = null;
                        $lifetimePromoPrice = null;

                        if ($promoActive) {
                            if ($isAnnualView && in_array($offer, ['free', 'monthly'], true)) {
                                $promoPrice = $plan['annual_promo'];
                                $promoLabel = __('First year');
                                $promoMonthlyEquivalent = $this->catalog()->formatCents(intdiv($plan['annual_promo_cents'], 12));
                            } elseif (! $isAnnualView && ! $isLifetimeView && $offer === 'free') {
                                $promoPrice = $plan['first_month'];
                                $promoLabel = __('First month');
                            } elseif ($isLifetimeView && $isFree) {
                                $lifetimePromoPrice = $plan['lifetime_promo'];
                            }
                        }
                    @endphp

                    <article
                        @class([
                            'relative flex flex-col rounded-3xl border bg-white p-5 pt-9 shadow-xs dark:bg-white/5',
                            'border-brand-green/25 dark:border-brand-sage/30' => $plan['highlighted'],
                            'border-brand-green/10 dark:border-white/10' => ! $plan['highlighted'],
                        ])
                    >
                        @if ($plan['highlighted'])
                            <span
                                class="bg-brand-green text-brand-cream absolute -top-3 left-5 rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-wide uppercase"
                            >
                                {{ __('Recommended') }}
                            </span>
                        @endif

                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-brand-ink dark:text-brand-cream text-lg font-semibold">
                                    {{ $plan['name'] }}
                                </h3>
                                <p class="text-brand-ink/55 dark:text-brand-sage/60 mt-0.5 text-xs">
                                    {{ __($plan['photos']) }}
                                </p>
                            </div>

                            @if ($promoPrice || $lifetimePromoPrice)
                                <x-ts-badge color="green" size="sm" variant="pill">
                                    {{ __('Black Friday') }}
                                </x-ts-badge>
                            @endif
                        </div>

                        <div class="mt-5">
                            @if ($isLifetimeView)
                                @if ($lifetimePromoPrice)
                                    <p class="text-brand-ink/40 dark:text-brand-sage/45 text-sm">
                                        {{ __('20% off') }}
                                    </p>

                                    <p
                                        class="text-brand-green dark:text-brand-sage mt-0.5 flex flex-wrap items-baseline gap-2"
                                    >
                                        <span class="text-3xl font-semibold tracking-tight">
                                            {{ $lifetimePromoPrice }}
                                        </span>
                                        <span class="text-brand-ink/45 dark:text-brand-sage/50 text-sm line-through">
                                            {{ $price['formatted'] }}
                                        </span>
                                    </p>
                                @else
                                    <p
                                        class="text-brand-green dark:text-brand-sage text-3xl font-semibold tracking-tight"
                                    >
                                        {{ $price['formatted'] }}
                                    </p>
                                @endif

                                <p class="text-brand-ink/55 dark:text-brand-sage/60 mt-1 text-xs">
                                    {{ __('one payment') }} · {{ __('No monthly or annual fees') }}
                                </p>
                            @elseif ($isAnnualView)
                                @if ($promoPrice)
                                    <p class="text-brand-ink/40 dark:text-brand-sage/45 text-sm">
                                        {{ $promoLabel }}
                                    </p>
                                @endif

                                <p class="text-brand-green dark:text-brand-sage text-3xl font-semibold tracking-tight">
                                    {{ $promoMonthlyEquivalent ?? $price['monthly_equivalent'] }}
                                </p>

                                <p class="text-brand-ink/55 dark:text-brand-sage/60 mt-1 text-xs">
                                    {{ __('per month') }}
                                </p>

                                <p class="text-brand-ink/70 dark:text-brand-sage/75 mt-2 text-sm font-semibold">
                                    {{ __('Billed annually: :price', ['price' => $promoPrice ?? $price['formatted']]) }}
                                    @if ($promoPrice)
                                        <span
                                            class="text-brand-ink/40 dark:text-brand-sage/45 ml-1 text-xs font-normal line-through"
                                        >
                                            {{ $price['formatted'] }}
                                        </span>
                                    @endif
                                </p>
                            @else
                                @if ($promoPrice)
                                    <p class="text-brand-ink/40 dark:text-brand-sage/45 text-sm">
                                        {{ $promoLabel }}
                                    </p>

                                    <p
                                        class="text-brand-green dark:text-brand-sage mt-0.5 flex flex-wrap items-baseline gap-2"
                                    >
                                        <span class="text-3xl font-semibold tracking-tight">{{ $promoPrice }}</span>
                                        <span class="text-brand-ink/45 dark:text-brand-sage/50 text-sm line-through">
                                            {{ $price['formatted'] }}
                                        </span>
                                    </p>

                                    <p class="text-brand-ink/55 dark:text-brand-sage/60 mt-1 text-xs">
                                        {{ __('Then :price per month.', ['price' => $price['formatted']]) }}
                                    </p>
                                @else
                                    <p
                                        class="text-brand-green dark:text-brand-sage text-3xl font-semibold tracking-tight"
                                    >
                                        {{ $price['formatted'] }}
                                    </p>

                                    <p class="text-brand-ink/55 dark:text-brand-sage/60 mt-1 text-xs">
                                        {{ __('per month') }}
                                    </p>
                                @endif
                            @endif

                            <p class="text-brand-ink/40 dark:text-brand-sage/45 mt-1 text-[11px] font-medium">
                                {{ __('VAT included') }}
                            </p>
                        </div>

                        <ul class="text-brand-ink/70 dark:text-brand-sage/75 mt-5 flex-1 space-y-2 text-xs font-medium">
                            <li class="flex items-start gap-2">
                                <x-icon
                                    name="check"
                                    class="text-brand-green dark:text-brand-sage mt-0.5 size-3.5 shrink-0"
                                />
                                {{ __(':storage of storage', ['storage' => $plan['name']]) }}
                            </li>
                            <li class="flex items-start gap-2">
                                <x-icon
                                    name="check"
                                    class="text-brand-green dark:text-brand-sage mt-0.5 size-3.5 shrink-0"
                                />
                                {{ __('Everything in the free plan') }}
                            </li>
                            <li class="flex items-start gap-2">
                                <x-icon
                                    name="check"
                                    class="text-brand-green dark:text-brand-sage mt-0.5 size-3.5 shrink-0"
                                />
                                {{ $isLifetimeView ? __('Private galleries keep their one-year expiry') : __('Galleries that never expire') }}
                            </li>
                            <li class="flex items-start gap-2">
                                <x-icon
                                    name="check"
                                    class="text-brand-green dark:text-brand-sage mt-0.5 size-3.5 shrink-0"
                                />
                                {{ __('Unlimited contracts, invoices and payments') }}
                            </li>
                            <li class="flex items-start gap-2">
                                <x-icon
                                    name="check"
                                    class="text-brand-green dark:text-brand-sage mt-0.5 size-3.5 shrink-0"
                                />
                                {{ __('White label branding') }}
                            </li>
                        </ul>

                        <div class="mt-5">
                            @if ($isGuest)
                                <x-ts-button
                                    :href="route('register', ['redirect' => route('subscribe', ['interval' => $displayInterval, 'plan' => $plan['key']], absolute: false)])"
                                    variant="primary"
                                    class="w-full"
                                >
                                    {{ __('Start now') }}
                                </x-ts-button>
                            @elseif ($isLifetimeView)
                                <x-ts-button
                                    wire:click="checkoutLifetime('{{ $plan['key'] }}')"
                                    wire:loading.attr="disabled"
                                    variant="primary"
                                    class="w-full"
                                    data-test="checkout-lifetime-{{ $plan['key'] }}"
                                >
                                    {{ __('Buy') }}
                                </x-ts-button>
                            @elseif ($isMonthly)
                                <x-ts-button
                                    wire:click="openUpgrade('{{ $plan['key'] }}')"
                                    variant="primary"
                                    class="w-full"
                                    data-test="upgrade-{{ $plan['key'] }}"
                                >
                                    {{ __('Switch to annual') }}
                                </x-ts-button>
                            @else
                                <x-ts-button
                                    wire:click="checkout('{{ $plan['key'] }}', '{{ $displayInterval }}')"
                                    wire:loading.attr="disabled"
                                    variant="primary"
                                    class="w-full"
                                    data-test="checkout-{{ $plan['key'] }}-{{ $displayInterval }}"
                                >
                                    {{ $isAnnualView ? __('Subscribe annually') : __('Subscribe monthly') }}
                                </x-ts-button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <x-subscription-benefits :lifetime="$displayInterval === 'lifetime'" />
    @endif
    @if ($promoActive && in_array($offer, ['free', 'monthly'], true) && $displayInterval !== 'lifetime')
        <section
            class="border-brand-coral/20 bg-brand-coral/5 dark:border-brand-coral/20 dark:bg-brand-coral/10 rounded-2xl border p-4"
        >
            <p class="text-brand-ink/65 dark:text-brand-sage/70 text-xs leading-relaxed">
                {{ __('Offer valid until :date. 1 €/5 € applies only to the first month; the normal monthly price applies afterwards. 20% applies to the first annual year and to the one-time plan; the normal price applies afterwards. Prices include VAT. Cannot be combined with other offers.', ['date' => $this->promoEndsLabel()]) }}
            </p>
        </section>
    @endif

    <x-ts-modal name="upgrade" class="w-full sm:max-w-lg">
        @if ($this->upgradeTier)
            <div class="p-5" data-test="upgrade-modal">
                <h3 class="text-brand-ink dark:text-brand-cream text-lg font-semibold">
                    {{ __('Switch to annual with 20% off') }}
                </h3>

                <p class="text-brand-ink/70 dark:text-brand-sage/75 mt-3 text-sm leading-relaxed">
                    {{ __('You pay nothing today.') }}
                    @if ($this->upgradeDate)
                        {{ __('On :date your plan will change to annual :plan.', ['date' => $this->upgradeDate, 'plan' => $this->upgradePlanName()]) }}
                    @else
                        {{ __('On your next renewal your plan will change to annual :plan.', ['plan' => $this->upgradePlanName()]) }}
                    @endif
                    {{ __('The 20% is applied to your first annual invoice: :price per year.', ['price' => $this->upgradePrice()]) }}
                </p>

                <p class="text-brand-ink/50 dark:text-brand-sage/55 mt-3 text-xs">
                    {{ __('The 20% applies only to the first year. After that the normal annual price applies. You keep your current monthly plan until the renewal date.') }}
                </p>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <x-ts-button type="button" variant="subtle" x-on:click="show = false">
                        {{ __('Cancel') }}
                    </x-ts-button>

                    <x-ts-button
                        type="button"
                        variant="primary"
                        wire:click="confirmUpgrade"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Schedule annual upgrade') }}
                    </x-ts-button>
                </div>
            </div>
        @endif
    </x-ts-modal>
</div>
