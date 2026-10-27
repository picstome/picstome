@props([
    'sortable' => false,
    'sorted' => false,
    'direction' => null,
])

@php
    $classes = [
        'px-3 py-3 first:pl-0 last:pr-0 border-b border-zinc-800/10 dark:border-white/20',
        'text-left text-sm font-medium text-zinc-800 dark:text-white',
        'cursor-pointer select-none' => $sortable,
    ];
@endphp

<th {{ $attributes->class($classes) }}>
    <div class="flex items-center gap-1 in-[.group\/center-align]:justify-center in-[.group\/right-align]:justify-end">
        {{ $slot }}

        @if ($sortable && $sorted)
            <x-icon
                :name="$direction === 'asc' ? 'chevron-up' : 'chevron-down'"
                class="size-3.5 shrink-0 text-zinc-700 dark:text-white"
            />
        @endif
    </div>
</th>
