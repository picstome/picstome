@props([
    'size' => null,
    'inline' => false,
])

@php
    $classes = match ($size) {
        'xl' => 'text-lg',
        'lg' => 'text-base',
        'sm' => 'text-xs',
        default => 'text-sm',
    };

    $hasCustomColor = (bool) preg_match('/(?:^|\s|:)text-(?!xs\b|sm\b|base\b|lg\b|xl\b|[2-9]xl\b)/', $attributes->get('class', ''));
@endphp

<p {{ $attributes->class([$classes, 'text-zinc-500 dark:text-white/70' => ! $hasCustomColor, 'inline' => $inline]) }}>
    {{ $slot }}
</p>
