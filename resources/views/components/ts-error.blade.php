@props([
    'name' => null,
    'property' => null,
])

<x-error :property="$property ?? $name" {{ $attributes }} />
