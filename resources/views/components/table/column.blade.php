@php
$classes = [
    'py-3 px-3 first:pl-0 last:pr-0',
    'text-left text-sm font-medium text-zinc-800 dark:text-white',
];
@endphp

<th {{ $attributes->class($classes) }}>
    <div class="flex in-[.group\/center-align]:justify-center in-[.group\/right-align]:justify-end">{{ $slot }}</div>
</th>
