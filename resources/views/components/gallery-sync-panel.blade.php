@props(['gallery'])

<div class="mx-auto max-w-3xl space-y-5 py-8">
    <x-ts-callout variant="secondary" icon="arrow-path">
        <x-ts-callout-heading>{{ __('Sync with Picstome Sync') }}</x-ts-callout-heading>

        <x-ts-callout-text>
            <p>
                {{ __('Open this gallery in the Picstome Sync desktop app to keep your photos synchronized without ZIP files.') }}
            </p>

            <ul class="mt-3 list-disc space-y-1 ps-5">
                <li>{{ __('Download new photos and upload changes from your computer.') }}</li>
                <li>{{ __('Keep the local copy up to date with the gallery.') }}</li>
                <li>{{ __('Rate your favorite images on your computer.') }}</li>
            </ul>
        </x-ts-callout-text>
    </x-ts-callout>

    <div class="flex flex-wrap items-center gap-4">
        <x-ts-button :href="$gallery->syncDeepLink()" variant="primary" icon="arrow-path">
            {{ __('Sync this gallery') }}
        </x-ts-button>

        <p class="text-sm text-zinc-500">
            {{ __("Don't have the app?") }}
            <x-ts-link href="https://github.com/picstome/picstome-sync/releases/latest" target="_blank">
                {{ __('Download Picstome Sync') }}
            </x-ts-link>
        </p>
    </div>

    <p class="text-xs text-zinc-500">
        {{ __('The app opens automatically. If nothing happens, install Picstome Sync first.') }}
    </p>
</div>
