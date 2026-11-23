{{--
    Branded email layout. `brand` is an App\Support\MailBranding; emails that
    do not pass one (everything sent to photographers) use the Picstome brand.
--}}
@props(['brand' => null])

@php
    $brand ??= \App\Support\MailBranding::picstome();
@endphp

<x-mail::layout>
{{-- Web font for the clients that load one (Apple Mail, iOS); the others use the system fallback in the theme. --}}
@if ($brand->fontUrl)
<x-slot:head>
<link href="{{ $brand->fontUrl }}" rel="stylesheet" type="text/css" />
</x-slot:head>
@endif

{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$brand->url">
@if ($brand->logoUrl)
<img src="{{ $brand->logoUrl }}" class="logo" alt="{{ $brand->name }}" @if ($brand->logoWidth) width="{{ $brand->logoWidth }}" @endif>
@else
<span class="brand-name">{{ $brand->name }}</span>
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $brand->name }}
@if ($brand->poweredByPicstome)

{{ __('Powered by') }} [Picstome](https://picstome.com)
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
