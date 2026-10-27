<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public bool $visible = false;

    public int $percent = 0;

    public string $upgradeUrl = '';

    public function mount(): void
    {
        $team = Auth::user()?->currentTeam;

        if (! $team) {
            return;
        }

        $this->visible = $team->shouldShowStorageAlert();
        $this->percent = (int) round($team->storageUsedPercentage());
        $this->upgradeUrl = $team->storageUpgradeUrl();
    }

    public function dismiss(): void
    {
        Auth::user()?->currentTeam?->dismissStorageAlert();

        $this->visible = false;
    }
}; ?>

<div>
    @if ($visible)
        <flux:callout icon="exclamation-triangle" variant="warning" class="mb-6" data-test="storage-alert-banner">
            <flux:callout.heading>{{ __('Your storage is almost full') }}</flux:callout.heading>

            <flux:callout.text>
                {{ __('You have used :percent% of your storage. When it fills up, you won\'t be able to upload more photos or videos.', ['percent' => $percent]) }}
            </flux:callout.text>

            <x-slot name="actions">
                <div class="flex items-center gap-2">
                    <flux:button :href="$upgradeUrl" variant="primary">
                        {{ __('See plans') }}
                    </flux:button>

                    <flux:button
                        variant="subtle"
                        icon="x-mark"
                        wire:click="dismiss"
                        :aria-label="__('Dismiss')"
                        data-test="storage-alert-dismiss"
                    />
                </div>
            </x-slot>
        </flux:callout>
    @endif
</div>
