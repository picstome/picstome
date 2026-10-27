@php
    $classes = [
        'table-fixed [:where(&)]:min-w-full border-separate border-spacing-0 isolate',
        'text-zinc-800',
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
