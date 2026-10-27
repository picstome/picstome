@props([
    'name' => 'modal',
    'variant' => null,
])

@php
    $event = str($name)->slug()->kebab();
    $classes = $attributes->get('class', '');
    $size = match (true) {
        str_contains($classes, 'max-w-7xl') => '7xl',
        str_contains($classes, 'max-w-6xl') => '6xl',
        str_contains($classes, 'max-w-5xl') => '5xl',
        str_contains($classes, 'max-w-4xl'), str_contains($classes, 'max-w-[794px]') => '4xl',
        str_contains($classes, 'max-w-3xl') => '3xl',
        str_contains($classes, 'max-w-2xl') => '2xl',
        str_contains($classes, 'max-w-xl'), str_contains($classes, 'max-w-[32rem]') => 'xl',
        str_contains($classes, 'max-w-lg'), str_contains($classes, 'max-w-[30rem]') => 'lg',
        str_contains($classes, 'max-w-md') => 'md',
        str_contains($classes, 'max-w-sm') => 'sm',
        default => '2xl',
    };
@endphp

@if ($variant === 'flyout')
    <div
        x-data
        x-on:modal:{{ $event }}-open.window="$tsui.open.slide('{{ $name }}')"
        x-on:modal:{{ $event }}-close.window="$tsui.close.slide('{{ $name }}')"
    >
        <x-slide
            :id="$name"
            :$size
            :paddingless="$variant === 'bare'"
            {{ $attributes }}
        >
            {{ $slot }}
        </x-slide>
    </div>
@else
    <x-modal
        :id="$name"
        :$size
        center
        :paddingless="$variant === 'bare'"
        :scrollable="str_contains($classes, 'overflow-y-auto')"
        {{ $attributes }}
    >
        @if ($variant !== 'bare')
            <button
                type="button"
                x-on:click="show = false"
                class="absolute end-4 top-4 z-10 rounded-md p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label="{{ __('Close modal') }}"
            >
                <x-icon name="x-mark" class="size-5" />
            </button>
        @endif

        {{ $slot }}
    </x-modal>
@endif
