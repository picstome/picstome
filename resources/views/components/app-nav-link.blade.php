@props([
    'href',
    'icon' => null,
    'current' => false,
    'target' => null,
    'quiet' => false,
])

<a
    href="{{ $href }}"
    @if ($target) target="{{ $target }}" @endif
    @if ($current) aria-current="page" @endif
    {{ $attributes->class([
        'my-px flex h-10 items-center gap-3 rounded-lg px-3 py-0 text-sm font-medium transition lg:h-8',
        'bg-brand-green text-brand-cream' => ! $quiet && $current,
        'text-brand-ink dark:text-brand-cream hover:bg-brand-sage-soft/80 dark:hover:bg-white/7' => ! $quiet && ! $current,
        'bg-zinc-100 text-zinc-800 dark:bg-white/10 dark:text-white' => $quiet && $current,
        'text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-white/5' => $quiet && ! $current,
    ]) }}
    @if (! $target) wire:navigate @endif
>
    @if ($icon)
        <x-icon
            :name="$icon"
            @class([
                'size-4 shrink-0',
                'text-brand-coral' => ! $quiet && ! $current,
                'text-brand-cream' => ! $quiet && $current,
                'text-zinc-500 dark:text-zinc-400' => $quiet,
            ])
        />
    @endif

    <span class="truncate">{{ $slot }}</span>
</a>
