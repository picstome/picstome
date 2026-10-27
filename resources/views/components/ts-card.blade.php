@props([
    'heading' => null,
    'icon' => null,
])

@php
    $classes = $attributes->get('class', '');
    $paddingless = str_contains($classes, 'p-0!') || str_contains($classes, 'p-0');
@endphp

<x-card :paddingless="$paddingless" {{ $attributes }}>
    @if ($heading)
        <x-slot:header>
            <div class="flex items-center gap-2">
                @if ($icon)
                    <x-icon :name="$icon" class="size-4" />
                @endif
                <span>{{ $heading }}</span>
            </div>
        </x-slot:header>
    @endif

    {{ $slot }}
</x-card>
