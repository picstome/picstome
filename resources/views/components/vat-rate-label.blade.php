@props(['rate'])

@php
    $value = (float) $rate;
    $label = $value === 0.0
        ? __('Not subject to VAT (0%)')
        : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
@endphp

{{ $label }}
