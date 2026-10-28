@props([
    'href' => null,
    'icon' => null,
])

<li {{ $attributes->class(['flex items-center gap-1.5']) }}>
    @if ($icon)
        <x-icon :name="$icon" class="size-4" />
    @endif

    @if ($href)
        <a href="{{ $href }}" class="transition hover:text-zinc-800 dark:hover:text-white" wire:navigate>
            {{ $slot }}
        </a>
    @else
        <span class="font-medium text-zinc-800 dark:text-white">{{ $slot }}</span>
    @endif
</li>
