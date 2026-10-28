@props([
    'variant' => null,
    'icon' => null,
    'color' => null,
])

@php
    $alertColor = $color ?? match ($variant) {
        'danger' => 'red',
        'warning' => 'yellow',
        'success' => 'green',
        'info' => 'blue',
        'secondary' => 'zinc',
        default => 'primary',
    };
@endphp

<div {{ $attributes->class(['w-full']) }}>
    <x-alert
        :$icon
        :color="$alertColor"
        :outline="$variant === 'outline'"
        :light="$variant !== 'outline'"
        rounded="xl"
        shadowless
    >
        {{ $slot }}

        @if (isset($actions))
            <x-slot:footer>
                <div class="mt-4">{{ $actions }}</div>
            </x-slot:footer>
        @endif
    </x-alert>
</div>
