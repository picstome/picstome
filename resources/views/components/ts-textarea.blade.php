@props([
    'label' => null,
    'description' => null,
    'rows' => null,
    'name' => null,
])

@php
    $name ??= $attributes->get('wire:model')
        ?? $attributes->get('wire:model.live')
        ?? $attributes->get('wire:model.defer')
        ?? $attributes->get('wire:model.blur');
@endphp

<x-textarea
    :$label
    :$rows
    :$name
    :hint="$description"
    {{ $attributes }}
>
    {{ trim((string) $slot) }}
</x-textarea>
