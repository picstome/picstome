@props([
    'label' => null,
    'description' => null,
    'placeholder' => null,
    'variant' => null,
    'size' => null,
    'searchable' => false,
    'multiple' => false,
    'name' => null,
])

@php
    $name ??= $attributes->get('wire:model')
        ?? $attributes->get('wire:model.live')
        ?? $attributes->get('wire:model.defer')
        ?? $attributes->get('wire:model.blur');

    $sizeClasses = match ($size) {
        'sm' => 'text-sm',
        'xs' => 'text-xs',
        'lg' => 'text-base',
        default => null,
    };
@endphp

<x-select.native
    :$label
    :hint="$description"
    @class([$sizeClasses, 'w-full'])
    {{ $attributes }}
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    {{ $slot }}
</x-select.native>
