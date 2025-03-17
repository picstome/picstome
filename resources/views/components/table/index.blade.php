@php
$classes = [
    '[:where(&)]:min-w-full table-fixed',
    'text-zinc-800',
    'divide-y divide-zinc-800/10 dark:divide-white/20 text-zinc-800',
    'whitespace-nowrap [&_dialog]:whitespace-normal [&_[popover]]:whitespace-normal',
];
@endphp

<div>
    {{ $header ?? '' }}

    <div class="overflow-x-auto">
        <table {{ $attributes->class($classes) }}>
            {{ $slot }}
        </table>
    </div>

    {{ $footer ?? '' }}
</div>
