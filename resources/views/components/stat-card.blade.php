@props(['label', 'value', 'hint' => null, 'clickable' => false])

@php
    $attributes = $attributes->class([
        'relative flex flex-col gap-1',
        'group cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700' => $clickable,
    ])->merge(array_filter([
        'role' => $clickable ? 'button' : null,
        'tabindex' => $clickable ? '0' : null,
        'x-on:keydown.enter' => $clickable ? '$el.click()' : null,
        'x-on:keydown.space.prevent' => $clickable ? '$el.click()' : null,
    ], fn ($value) => $value !== null));
@endphp

<flux:card {{ $attributes }}>
    @if ($clickable)
        <flux:icon.chart-bar
            class="absolute top-4 right-4 size-4 text-zinc-400 transition-colors group-hover:text-zinc-600 dark:group-hover:text-zinc-200"
        />
    @endif

    <flux:text class="text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
        {{ $label }}
    </flux:text>

    <flux:heading size="xl" class="tabular-nums">{{ $value }}</flux:heading>

    @if ($hint)
        <flux:text class="text-xs">{{ $hint }}</flux:text>
    @endif
</flux:card>
