<?php

use App\Services\AccountSummaryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    /** @var array<string, mixed> */
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = app(AccountSummaryService::class)->forTeam(Auth::user()->currentTeam);
    }
}; ?>

<div>
    <div
        class="border-brand-green/15 mx-2 rounded-xl border bg-white p-3 shadow-xs dark:border-white/10 dark:bg-white/5"
        data-test="account-summary"
    >
        <div class="flex items-start gap-2">
            <x-icon name="credit-card" class="text-brand-green dark:text-brand-sage mt-0.5 size-4 shrink-0" />

            <p class="text-brand-ink dark:text-brand-cream min-w-0 truncate text-xs font-semibold">
                {{ __('Plan :plan', ['plan' => $summary['plan_label']]) }}
            </p>
        </div>

        <p class="text-brand-ink/65 dark:text-brand-sage/75 mt-2 text-[11px] font-medium">
            {{ $summary['storage_used'] }} / {{ $summary['storage_total'] }}
            @unless ($summary['has_unlimited_storage'])
                · {{ $summary['storage_percent'] }}%
            @endunless
        </p>

        @unless ($summary['has_unlimited_storage'])
            <div class="bg-brand-sage/30 mt-2 h-1.5 w-full overflow-hidden rounded-full dark:bg-white/10">
                <div
                    class="{{ (float) $summary['storage_percent'] > 90 ? 'bg-brand-coral' : ((float) $summary['storage_percent'] > 75 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-sage') }} h-full rounded-full transition-all duration-300"
                    style="width: {{ min((float) $summary['storage_percent'], 100) }}%"
                ></div>
            </div>
        @endunless

        @if ($summary['renews_at_label'])
            <p class="text-brand-ink/50 dark:text-brand-sage/60 mt-2 text-[11px]">
                {{ $summary['renews_at_label'] }}
            </p>
        @endif

        <x-ts-button :href="$summary['subscription_url']" size="sm" variant="primary" class="mt-3 w-full">
            {{ __('Subscription') }}
        </x-ts-button>
    </div>
</div>
