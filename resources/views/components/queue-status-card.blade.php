@props([
    'label',
    'value',
    'hint' => null,
    'status' => 'zinc',
    'icon' => null,
])

@php
    $tones = [
        'green' => [
            'ring' => 'bg-emerald-500/10',
            'text' => 'text-emerald-700 dark:text-emerald-300',
            'dot' => 'bg-emerald-500',
        ],
        'red' => [
            'ring' => 'bg-red-500/10',
            'text' => 'text-red-700 dark:text-red-300',
            'dot' => 'bg-red-500',
        ],
        'amber' => [
            'ring' => 'bg-amber-500/10',
            'text' => 'text-amber-700 dark:text-amber-300',
            'dot' => 'bg-amber-500',
        ],
        'blue' => [
            'ring' => 'bg-sky-500/10',
            'text' => 'text-sky-700 dark:text-sky-300',
            'dot' => 'bg-sky-500',
        ],
        'zinc' => [
            'ring' => 'bg-zinc-500/10',
            'text' => 'text-zinc-600 dark:text-zinc-300',
            'dot' => 'bg-zinc-400',
        ],
    ];

    $tone = $tones[$status] ?? $tones['zinc'];
    $attributes = $attributes->class(['relative flex flex-col gap-1']);
@endphp

<x-ts-card {{ $attributes }}>
    <div class="flex items-center gap-2">
        <span class="{{ $tone['ring'] }} flex size-7 shrink-0 items-center justify-center rounded-full">
            @if ($icon)
                <x-icon :name="$icon" class="{{ $tone['text'] }} size-4" />
            @else
                <span class="{{ $tone['dot'] }} size-2 rounded-full"></span>
            @endif
        </span>

        <x-ts-text class="{{ $tone['text'] }} text-xs font-semibold tracking-wide uppercase">
            {{ $label }}
        </x-ts-text>
    </div>

    <x-ts-heading size="xl" class="tabular-nums">{{ $value }}</x-ts-heading>

    @if ($hint)
        <x-ts-text class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $hint }}</x-ts-text>
    @endif
</x-ts-card>
