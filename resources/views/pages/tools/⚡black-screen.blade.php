<?php

use Livewire\Component;

new class extends Component {}; ?>

<div class="mx-auto max-w-lg">
    <div class="relative mb-6 w-full">
        <x-ts-heading size="xl" level="1">{{ __('Black Screen') }}</x-ts-heading>
        <x-ts-subheading size="lg" class="mb-6">
            {{ __('Turn your screen completely black. Perfect for cleaning your monitor, saving energy, or keeping your computer awake.') }}
        </x-ts-subheading>
        <x-ts-separator variant="subtle" />
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-6 py-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-black text-white shadow-lg">
            <x-icon name="computer-desktop" variant="outline" class="size-8" />
        </div>

        <x-ts-button
            x-data
            x-on:click="$dispatch('black-screen:start')"
            variant="primary"
            icon="bolt"
            class="w-full px-10! py-3! text-base! sm:w-auto"
            aria-keyshortcuts="Meta+B Control+B"
            data-black-screen-start
        >
            {{ __('Go Black') }}
        </x-ts-button>

        <x-ts-text size="sm" class="mt-4">
            {{ __('Press ESC or click anywhere to exit.') }}
        </x-ts-text>

        <x-ts-text size="sm" class="mt-1">
            {{ __('You can also launch it from anywhere in the app with Ctrl+B or ⌘B.') }}
        </x-ts-text>
    </div>

    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <x-ts-badge icon="sparkles">{{ __('Clean your screen') }}</x-ts-badge>
        <x-ts-badge icon="bolt">{{ __('Save energy') }}</x-ts-badge>
        <x-ts-badge icon="eye-slash">{{ __('Keep computer awake') }}</x-ts-badge>
    </div>
</div>
