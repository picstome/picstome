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
        <x-ts-callout icon="exclamation-triangle" variant="warning" class="mb-6" data-test="storage-alert-banner">
            <x-ts-callout-heading>{{ __('Your storage is almost full') }}</x-ts-callout-heading>

            <x-ts-callout-text>
                {{ __('You have used :percent% of your storage. When it fills up, you won\'t be able to upload more photos or videos.', ['percent' => $percent]) }}
            </x-ts-callout-text>

            <x-slot name="actions">
                <div class="flex items-center gap-2">
                    <x-ts-button :href="$upgradeUrl" variant="primary">
                        {{ __('See plans') }}
                    </x-ts-button>

                    <x-ts-button
                        variant="subtle"
                        icon="x-mark"
                        wire:click="dismiss"
                        :aria-label="__('Dismiss')"
                        data-test="storage-alert-dismiss"
                    />
                </div>
            </x-slot>
        </x-ts-callout>
    @endif
</div>
