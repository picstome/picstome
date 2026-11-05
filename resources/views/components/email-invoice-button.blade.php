@props([
    'invoice',
    'size' => null,
    'variant' => 'ghost',
    'compact' => false,
])

@php
    $url = app(\App\Services\InvoiceMailtoService::class)->url($invoice);
@endphp

@if ($url)
    <x-ts-button
        :$size
        :$variant
        icon="envelope"
        :href="$url"
        :aria-label="__('Email invoice')"
        {{ $attributes }}
    >
        <span @class(['hidden xl:inline' => $compact])>{{ __('Email invoice') }}</span>
    </x-ts-button>
@endif
