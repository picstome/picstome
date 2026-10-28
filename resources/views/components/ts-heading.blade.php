@props([
    'size' => null,
    'level' => 2,
    'inline' => false,
])

@php
    $tag = 'h'.(int) $level;
    $classes = match ($size) {
        '2xl' => 'text-4xl',
        'xl' => 'text-2xl',
        'lg' => 'text-base',
        default => 'text-sm',
    };

    $hasCustomColor = (bool) preg_match('/(?:^|\s|:)text-(?!xs\b|sm\b|base\b|lg\b|xl\b|[2-9]xl\b)/', $attributes->get('class', ''));
@endphp

<{{ $tag }} {{ $attributes->class([$classes, 'font-medium', 'inline' => $inline, 'text-zinc-800 dark:text-white' => ! $hasCustomColor]) }}>
    {{ $slot }}
</{{ $tag }}>
