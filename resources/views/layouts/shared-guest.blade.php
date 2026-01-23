@props(['font' => null, 'color' => null])

<x-guest-layout :font="$font" :color="$color">
    {{ $slot }}
</x-guest-layout>
