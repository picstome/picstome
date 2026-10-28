@props([
    'name' => null,
    'icon' => null,
    'variant' => null,
])

<x-icon :name="$name ?? $icon" {{ $attributes }} />
