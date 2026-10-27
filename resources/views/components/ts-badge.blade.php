@props([
    'color' => 'zinc',
    'size' => null,
    'variant' => null,
    'icon' => null,
    'inset' => null,
    'as' => null,
])

@php
    $classes = $attributes->get('class', '');
    $badgeSize = $size === 'sm' ? 'xs' : ($size === 'lg' ? 'md' : 'sm');
    $isOutline = $variant === 'outline';
@endphp

<x-badge
    :$icon
    :$color
    :xs="$badgeSize === 'xs'"
    :sm="$badgeSize === 'sm'"
    :md="$badgeSize === 'md'"
    :lg="$badgeSize === 'lg'"
    :solid="$variant === 'solid'"
    :outline="$isOutline"
    :light="$variant !== 'solid' && ! $isOutline"
    :round="$variant === 'pill'"
    @class([$classes, 'py-1 px-2', 'border-0' => ! $isOutline])
    {{ $attributes->except('class') }}
>
    {{ $slot }}
</x-badge>
