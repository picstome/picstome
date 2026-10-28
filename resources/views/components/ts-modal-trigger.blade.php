@props([
    'name',
    'shortcut' => null,
])

@php
    $event = str($name)->slug()->kebab();
@endphp

<span
    class="contents"
    x-on:click="$dispatch('modal:{{ $event }}-open')"
    @if ($shortcut) x-on:keydown.window.prevent.{{ $shortcut }}="$dispatch('modal:{{ $event }}-open')" @endif
    {{ $attributes }}
>
    {{ $slot }}
</span>
