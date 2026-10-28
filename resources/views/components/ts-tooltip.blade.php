@props([
    'content' => null,
    'toggleable' => false,
])

<span x-data x-tooltip="{{ $content }}" {{ $attributes }}>
    {{ $slot }}
</span>
