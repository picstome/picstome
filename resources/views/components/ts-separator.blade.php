@props([
    'variant' => null,
    'text' => null,
])

@if ($text)
    <div {{ $attributes->class(['flex items-center gap-3 text-xs font-medium tracking-wide text-zinc-400 uppercase dark:text-zinc-500']) }}>
        <span class="h-px flex-1 bg-zinc-200 dark:bg-white/10"></span>
        <span>{{ $text }}</span>
        <span class="h-px flex-1 bg-zinc-200 dark:bg-white/10"></span>
    </div>
@else
    <hr {{ $attributes->class([
        'border-0 border-t',
        $variant === 'subtle' ? 'border-zinc-100 dark:border-white/5' : 'border-zinc-200 dark:border-white/10',
    ]) }} />
@endif
