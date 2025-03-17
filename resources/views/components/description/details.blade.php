<dd
    {{ $attributes->except('class') }}
    @class([
        $attributes->get('class'),
        'pt-1 pb-3 text-zinc-950 sm:border-t sm:border-zinc-950/5 sm:py-3 sm:nth-2:border-none dark:text-white dark:sm:border-white/5',
    ])
>
    {{ $slot }}
</dd>
