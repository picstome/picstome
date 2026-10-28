@props(['label', 'value', 'hint' => null, 'clickable' => false, 'tone' => null])

@php
    $isInk = $tone === 'ink';
    $isGreen = $tone === 'green';
    $hasTone = $isInk || $isGreen;

    $attributes = $attributes->class([
        'relative flex flex-col gap-1',
        'border-brand-green/15 dark:border-white/10' => ! $hasTone,
        'border-brand-ink bg-brand-ink text-brand-cream' => $isInk,
        'border-brand-green bg-brand-green text-brand-cream' => $isGreen,
        'group cursor-pointer transition-colors' => $clickable,
        'hover:bg-brand-sage/20 dark:hover:bg-white/10' => $clickable && ! $hasTone,
        'hover:bg-brand-ink/90' => $clickable && $isInk,
        'hover:bg-brand-green/90' => $clickable && $isGreen,
    ])->merge(array_filter([
        'role' => $clickable ? 'button' : null,
        'tabindex' => $clickable ? '0' : null,
        'x-on:keydown.enter' => $clickable ? '$el.click()' : null,
        'x-on:keydown.space.prevent' => $clickable ? '$el.click()' : null,
    ], fn ($value) => $value !== null));

    $iconClasses = $hasTone
        ? 'text-brand-cream/60 group-hover:text-brand-cream absolute top-4 right-4 size-4 transition-colors'
        : 'text-brand-green/50 group-hover:text-brand-coral dark:text-brand-sage/60 dark:group-hover:text-brand-coral absolute top-4 right-4 size-4 transition-colors';

    $labelClasses = $hasTone
        ? 'text-brand-cream'
        : 'text-brand-green-deep dark:text-brand-sage';

    $hintClasses = $hasTone
        ? 'text-brand-cream/80'
        : 'text-brand-ink/70 dark:text-brand-sage/80';
@endphp

<x-ts-card {{ $attributes }}>
    @if ($clickable)
        <x-icon name="chart-bar" class="{{ $iconClasses }}" />
    @endif

    <x-ts-text class="text-xs font-semibold tracking-wide uppercase {{ $labelClasses }}">
        {{ $label }}
    </x-ts-text>

    <x-ts-heading size="xl" class="tabular-nums {{ $hasTone ? 'text-brand-cream dark:text-brand-cream' : '' }}">
        {{ $value }}
    </x-ts-heading>

    @if ($hint)
        <x-ts-text class="text-xs font-medium {{ $hintClasses }}">{{ $hint }}</x-ts-text>
    @endif
</x-ts-card>
