<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public $used = 0;

    public $total = 0;

    public $usage = null;

    public bool $hasUnlimitedStorage = false;

    public function mount(): void
    {
        $team = Auth::user()->currentTeam;

        $this->used = $team->storage_used_gb;
        $this->total = $team->storage_limit_gb;
        $this->usage = $team->storage_used_percent;
        $this->hasUnlimitedStorage = $team->hasUnlimitedStorage;
    }
}; ?>

<div>
    <div
        class="border-brand-green/15 mx-2 rounded-xl border bg-white p-3 shadow-xs dark:border-white/10 dark:bg-white/5"
    >
        <div class="mb-2 flex items-center gap-2">
            <flux:icon.server class="text-brand-green dark:text-brand-sage size-4" />
            <flux:text class="text-brand-green-deep dark:text-brand-sage text-xs font-medium">
                {{ __('Storage') }}
            </flux:text>
        </div>

        <div class="space-y-1">
            <div class="flex justify-between text-xs">
                <span class="text-brand-ink/70 dark:text-brand-sage/80 font-medium">{{ __('Used') }}</span>
                <span class="text-brand-ink dark:text-brand-cream font-mono">{{ $used }}</span>
            </div>

            @unless ($hasUnlimitedStorage)
                <div class="flex justify-between text-xs">
                    <span class="text-brand-ink/70 dark:text-brand-sage/80 font-medium">{{ __('Total') }}</span>
                    <span class="text-brand-ink dark:text-brand-cream font-mono">{{ $total }}</span>
                </div>
            @else
                <div class="flex justify-between text-xs">
                    <span class="text-brand-ink/70 dark:text-brand-sage/80 font-medium">{{ __('Total') }}</span>
                    <span class="text-brand-ink dark:text-brand-cream font-mono">{{ __('Unlimited') }}</span>
                </div>
            @endunless

            @unless ($hasUnlimitedStorage)
                <div class="mt-2">
                    <div class="mb-1 flex justify-between text-xs">
                        <span class="text-brand-ink/70 dark:text-brand-sage/80 font-medium">{{ __('Usage') }}</span>
                        <span class="text-brand-ink dark:text-brand-cream font-mono">{{ $usage }}%</span>
                    </div>
                    <div class="bg-brand-sage/30 h-1.5 w-full overflow-hidden rounded-full dark:bg-white/10">
                        <div
                            class="{{ $usage > 90 ? 'bg-brand-coral' : ($usage > 75 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-sage') }} h-full rounded-full transition-all duration-300"
                            style="width: {{ min($usage, 100) }}%"
                        ></div>
                    </div>
                </div>
            @endunless
        </div>
    </div>
</div>
