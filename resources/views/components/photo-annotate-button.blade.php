@props([
    'canAnnotate' => false,
])

@if ($canAnnotate)
    <x-ts-button
        icon="map-pin"
        size="sm"
        class="max-sm:hidden"
        x-data="{ active: false }"
        x-on:annotations-toggled.window="active = $event.detail.active"
        x-on:click="$dispatch('toggle-annotations')"
        x-bind:class="active ? 'bg-(--color-accent)! text-(--color-accent-foreground)!' : ''"
    >
        <span x-show="!active">{{ __('Annotate') }}</span>
        <span x-show="active" x-cloak>{{ __('Exit annotation mode') }}</span>
    </x-ts-button>

    <x-ts-button
        icon="map-pin"
        size="sm"
        class="sm:hidden"
        square
        x-data="{ active: false }"
        x-on:annotations-toggled.window="active = $event.detail.active"
        x-on:click="$dispatch('toggle-annotations')"
        x-bind:class="active ? 'bg-(--color-accent)! text-(--color-accent-foreground)!' : ''"
    />
@endif
