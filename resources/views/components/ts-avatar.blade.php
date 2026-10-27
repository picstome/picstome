@props([
    'src' => null,
    'name' => null,
    'icon' => null,
    'color' => null,
    'size' => null,
    'circle' => true,
    'tooltip' => null,
])

@php
    $sizeClasses = match ($size) {
        'xs' => 'size-6',
        'sm' => 'size-8',
        'lg' => 'size-12',
        'xl' => 'size-16',
        default => 'size-10',
    };
@endphp

@if ($icon)
    <div
        title="{{ $tooltip }}"
        @if ($color)
            style="background-color: color-mix(in oklab, var(--color-{{ $color }}-500) 15%, transparent); color: var(--color-{{ $color }}-600);"
        @endif
        {{ $attributes->class([
            'flex shrink-0 items-center justify-center rounded-full',
            $sizeClasses,
            'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300' => ! $color,
        ]) }}
    >
        <x-icon :name="$icon" class="size-1/2" />
    </div>
@else
    <x-avatar
        :image="$src"
        :text="$name"
        :$size
        :square="! $circle"
        :title="$tooltip"
        {{ $attributes }}
    />
@endif
