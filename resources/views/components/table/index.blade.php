@php
    $classes = [
        'table-fixed [:where(&)]:min-w-full',
        'text-zinc-800',
        'divide-y divide-zinc-800/10 text-zinc-800 dark:divide-white/20',
        'whitespace-nowrap [&_[popover]]:whitespace-normal [&_dialog]:whitespace-normal',
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
