@props([
    'variant' => null,
])

@php
    $classes = [
        'px-3 py-3 text-sm first:pl-0 last:pr-0',
        'font-medium text-zinc-800 dark:text-white' => $variant === 'strong',
        'text-zinc-500 dark:text-zinc-300' => is_null($variant),
    ];
@endphp

<td {{ $attributes->class($classes) }}>
    {{ $slot }}
</td>
