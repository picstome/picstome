<nav {{ $attributes->class(['flex']) }} aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400">
        {{ $slot }}
    </ol>
</nav>
