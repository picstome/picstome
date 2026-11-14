@php
    $planName = $this->planName($currentTier) ?? __('Active plan');
    $statusLabel = match ($state) {
        'monthly' => __('Monthly'),
        'annual' => __('Annual'),
        'lifetime' => __('One-time'),
        default => __('Active'),
    };
@endphp

<section
    class="border-brand-green/10 rounded-3xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5"
    data-test="current-plan"
>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-brand-ink/45 dark:text-brand-sage/50 text-[11px] font-semibold tracking-wide uppercase">
                {{ __('Your current plan') }}
            </p>

            <h2 class="text-brand-ink dark:text-brand-cream mt-1 text-xl font-semibold">
                {{ $planName }} · {{ $statusLabel }}
            </h2>

            <p class="text-brand-ink/60 dark:text-brand-sage/65 mt-1 text-sm">
                @if ($state === 'monthly')
                    {{ __('You can switch to annual at your next renewal without paying anything today.') }}
                @elseif ($state === 'annual')
                    {{ __('Your annual plan renews automatically. You can download invoices and manage payment details from Stripe.') }}
                @else
                    {{ __('Your one-time plan does not renew. Private galleries keep their one-year expiry.') }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($state === 'lifetime')
                <x-ts-button :href="route('dashboard')" variant="primary" size="sm">
                    {{ __('Dashboard') }}
                </x-ts-button>
            @else
                <x-ts-button :href="$this->manageBillingUrl()" variant="primary" size="sm">
                    {{ __('Manage subscription') }}
                </x-ts-button>
            @endif
        </div>
    </div>

    @if ($scheduled)
        <div
            class="border-brand-green/15 bg-brand-sage/15 dark:border-brand-sage/20 dark:bg-brand-sage/10 mt-4 rounded-2xl border p-4"
            data-test="scheduled-change"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-brand-green dark:text-brand-sage text-sm font-semibold">
                        {{ __('Annual upgrade scheduled') }}
                    </p>

                    <p class="text-brand-ink/65 dark:text-brand-sage/70 mt-1 text-xs">
                        {{
                            __('On :date your plan will change to annual :plan and the 20% will be applied to your first annual invoice.', [
                                'date' => $this->scheduledChangeDate(),
                                'plan' => $this->scheduledChangePlanName() ?? $planName,
                            ])
                        }}
                    </p>
                </div>

                <x-ts-button
                    type="button"
                    variant="subtle"
                    size="sm"
                    wire:click="cancelScheduledChange"
                    wire:loading.attr="disabled"
                    data-test="cancel-scheduled-change"
                >
                    {{ __('Cancel scheduled change') }}
                </x-ts-button>
            </div>
        </div>
    @endif
</section>
