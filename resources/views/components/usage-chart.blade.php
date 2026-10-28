@props(['series', 'label'])

@php
    $labels = collect($series)->pluck('label')->all();
    $values = collect($series)->pluck('value')->all();
    $chartSeries = [['name' => $label, 'data' => $values]];
@endphp

<x-chart
    :labels="$labels"
    :series="$chartSeries"
    type="bar"
    grid
    tooltip
    :height="260"
    color="primary"
    :formatter="fn ($value) => number_format((float) $value)"
    class="w-full"
/>
