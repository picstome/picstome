@props(['label', 'value', 'hint' => null, 'clickable' => false])

@php
    $attributes = $attributes->class([
        'group flex w-full items-center justify-between gap-4 rounded-lg px-3 py-2.5 text-left',
        'cursor-pointer transition-colors hover:bg-brand-ink/5 dark:hover:bg-white/10' => $clickable,
    ])->merge(array_filter([
        'role' => $clickable ? 'button' : null,
        'tabindex' => $clickable ? '0' : null,
        'x-on:keydown.enter' => $clickable ? '$el.click()' : null,
        'x-on:keydown.space.prevent' => $clickable ? '$el.click()' : null,
    ], fn ($value) => $value !== null));
@endphp

<div {{ $attributes }}>
    <span class="min-w-0">
        <span class="text-brand-ink dark:text-brand-cream block truncate text-sm font-medium">
            {{ $label }}
        </span>

        @if ($hint)
            <span class="text-brand-ink/55 dark:text-brand-sage/65 mt-0.5 block truncate text-xs font-medium">
                {{ $hint }}
            </span>
        @endif
    </span>

    <span class="flex shrink-0 items-center gap-2">
        <span class="text-brand-green dark:text-brand-sage text-lg font-semibold tabular-nums">
            {{ $value }}
        </span>

        @if ($clickable)
            <x-icon name="chart-bar" class="text-brand-green/35 group-hover:text-brand-coral dark:text-brand-sage/35 dark:group-hover:text-brand-coral size-4 transition-colors" />
        @endif
    </span>
</div>
