<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public $used = 0;
    public $total = 0;
    public $usage = null;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->used = $team->storage_used_gb;
        $this->total = $team->storage_limit_gb;
        $this->usage = $team->storage_used_percent;
    }
}; ?>

<div>
    <div class="mx-2 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex items-center gap-2 mb-2">
            <flux:icon.server class="size-4 text-zinc-500 dark:text-zinc-400" />
            <flux:text class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                {{ __('Storage') }}
            </flux:text>
        </div>

        <div class="space-y-1">
            <div class="flex justify-between text-xs">
                <span class="text-zinc-600 dark:text-zinc-400">{{ __('Used') }}</span>
                <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $used }}</span>
            </div>

            @unless (Auth::user()->currentTeam->hasUnlimitedStorage)
                <div class="flex justify-between text-xs">
                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('Total') }}</span>
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $total }}</span>
                </div>
            @else
                <div class="flex justify-between text-xs">
                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('Total') }}</span>
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ __('Unlimited') }}</span>
                </div>
            @endunless

            @unless (Auth::user()->currentTeam->hasUnlimitedStorage)
                <div class="mt-2">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Usage') }}</span>
                        <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $usage }}%</span>
                    </div>
                    <div class="w-full bg-zinc-200 rounded-full h-1.5 dark:bg-zinc-700">
                        <div
                            class="h-1.5 rounded-full transition-all duration-300 {{ $usage > 90 ? 'bg-red-500' : ($usage > 75 ? 'bg-yellow-500' : 'bg-blue-500') }}"
                            style="width: {{ min($usage, 100) }}%"
                        ></div>
                    </div>
                </div>
            @endunless
        </div>
    </div>
</div>
