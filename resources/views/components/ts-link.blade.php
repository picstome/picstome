@props([
    'href' => null,
    'variant' => null,
    'accent' => true,
    'icon' => null,
    'iconTrailing' => null,
])

@php
    $linkIcon = $iconTrailing ?? $icon;
    $position = $iconTrailing ? 'right' : 'left';
@endphp

@if ($href)
    <x-link
        :$href
        :icon="$linkIcon"
        :$position
        :underline="$variant === 'subtle'"
        :colorless="$variant === 'ghost' || $accent === false"
        {{ $attributes }}
    >
        {{ $slot }}
    </x-link>
@else
    <button type="button" {{ $attributes->class(['inline-flex items-center gap-1 text-left text-primary-600 hover:underline dark:text-primary-400']) }}>
        @if ($linkIcon && ! $iconTrailing)
            <x-icon :name="$linkIcon" class="size-4" />
        @endif

        {{ $slot }}

        @if ($linkIcon && $iconTrailing)
            <x-icon :name="$linkIcon" class="size-4" />
        @endif
    </button>
@endif
