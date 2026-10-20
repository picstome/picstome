<?php

use Livewire\Component;

new class extends Component {}; ?>

<div class="mx-auto max-w-lg">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Black Screen') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">
            {{ __('Turn your screen completely black. Perfect for cleaning your monitor, saving energy, or keeping your computer awake.') }}
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-6 py-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-black text-white shadow-lg">
            <flux:icon.computer-desktop variant="outline" class="size-8" />
        </div>

        <flux:button
            x-data
            x-on:click="$dispatch('black-screen:start')"
            variant="primary"
            icon="bolt"
            class="w-full px-10! py-3! text-base! sm:w-auto"
            data-black-screen-start
        >
            {{ __('Go Black') }}
        </flux:button>

        <flux:text size="sm" class="mt-4">
            {{ __('Press ESC or click anywhere to exit.') }}
        </flux:text>
    </div>

    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <flux:badge icon="sparkles">{{ __('Clean your screen') }}</flux:badge>
        <flux:badge icon="bolt">{{ __('Save energy') }}</flux:badge>
        <flux:badge icon="eye-slash">{{ __('Keep computer awake') }}</flux:badge>
    </div>
</div>
