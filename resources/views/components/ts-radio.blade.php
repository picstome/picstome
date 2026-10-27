@props([
    'label' => null,
    'description' => null,
    'size' => null,
])

<div class="space-y-1 {{ $attributes->get('class') }}">
    <x-radio
        :$label
        :xs="$size === 'xs' ? true : null"
        :sm="in_array($size, [null, 'sm', 'mini', 'micro'], true) ? true : null"
        :lg="$size === 'lg' ? true : null"
        {{ $attributes->except('class') }}
    />

    @if ($description)
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif
</div>
