@props([
    'canAnnotate' => false,
])

@if ($canAnnotate)
    <x-ts-button
        icon="map-pin"
        size="sm"
        variant="primary"
        class="max-sm:hidden"
        data-annotate-button
        x-data="{ active: false }"
        x-on:annotations-toggled.window="active = $event.detail.active"
        x-on:click="$dispatch('toggle-annotations')"
        x-bind:class="active ? 'bg-brand-sage! text-brand-ink-strong!' : ''"
    >
        <span x-show="!active">{{ __('Annotate') }}</span>
        <span x-show="active" x-cloak>{{ __('Exit annotation mode') }}</span>
    </x-ts-button>

    <x-ts-button
        icon="map-pin"
        size="sm"
        variant="primary"
        class="sm:hidden"
        square
        data-annotate-button
        x-data="{ active: false }"
        x-on:annotations-toggled.window="active = $event.detail.active"
        x-on:click="$dispatch('toggle-annotations')"
        x-bind:class="active ? 'bg-brand-sage! text-brand-ink-strong!' : ''"
    />
@endif
