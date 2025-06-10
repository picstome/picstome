<?php

use App\Services\DiskSpaceService;
use Livewire\Volt\Component;

new class extends Component
{
    public function with()
    {
        if (!DiskSpaceService::isLocalPublicDisk()) {
            return ['showDiskSpace' => false];
        }

        return [
            'showDiskSpace' => true,
            'freeSpace' => DiskSpaceService::formatBytes(DiskSpaceService::getFreeDiskSpace()),
            'usedSpace' => DiskSpaceService::formatBytes(DiskSpaceService::getUsedDiskSpace()),
            'usagePercentage' => DiskSpaceService::getUsagePercentage(),
        ];
    }
}; ?>

<div>
    @if ($showDiskSpace)
        <div class="mx-2 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon.server class="size-4 text-zinc-500 dark:text-zinc-400" />
                <flux:text class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                    {{ __('Disk Space') }}
                </flux:text>
            </div>

            <div class="space-y-1">
                <div class="flex justify-between text-xs">
                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('Used') }}</span>
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $usedSpace }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('Free') }}</span>
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $freeSpace }}</span>
                </div>

                @if ($usagePercentage !== null)
                    <div class="mt-2">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Usage') }}</span>
                            <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $usagePercentage }}%</span>
                        </div>
                        <div class="w-full bg-zinc-200 rounded-full h-1.5 dark:bg-zinc-700">
                            <div
                                class="h-1.5 rounded-full transition-all duration-300 {{ $usagePercentage > 90 ? 'bg-red-500' : ($usagePercentage > 75 ? 'bg-yellow-500' : 'bg-blue-500') }}"
                                style="width: {{ min($usagePercentage, 100) }}%"
                            ></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
