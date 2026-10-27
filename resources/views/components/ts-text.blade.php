@props([
    'size' => null,
    'variant' => null,
    'inline' => false,
])

@php
    $classes = match ($size) {
        'xl' => 'text-lg',
        'lg' => 'text-base',
        'sm' => 'text-xs',
        default => 'text-sm',
    };

    $classes .= ' font-normal';

    $hasCustomColor = (bool) preg_match('/(?:^|\s|:)text-(?!xs\b|sm\b|base\b|lg\b|xl\b|[2-9]xl\b)/', $attributes->get('class', ''));

    $color = match ($variant) {
        'strong' => 'text-zinc-800 dark:text-white',
        'subtle' => 'text-zinc-400 dark:text-white/50',
        default => 'text-zinc-500 dark:text-white/70',
    };
@endphp

<p {{ $attributes->class([$classes, $color => ! $hasCustomColor, 'inline' => $inline]) }}>
    {{ $slot }}
</p>
