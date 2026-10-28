@props([
    'paginator',
])

<div {{ $attributes->class(['flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700']) }}>
    <div class="text-xs font-medium whitespace-nowrap text-zinc-500 dark:text-zinc-400">
        @if ($paginator->total() > 0)
            {{ __('Showing') }} {{ $paginator->firstItem() }} {{ __('to') }} {{ $paginator->lastItem() }}
            {{ __('of') }} {{ $paginator->total() }} {{ __('results') }}
        @endif
    </div>

    @if ($paginator->hasPages())
        <div class="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-px dark:border-white/10 dark:bg-white/10">
            @if ($paginator->onFirstPage())
                <span class="flex size-7 items-center justify-center text-zinc-300 dark:text-zinc-500">
                    <x-icon name="chevron-left" class="size-4" />
                </span>
            @else
                <button
                    type="button"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    class="flex size-7 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    aria-label="{{ __('pagination.previous') }}"
                >
                    <x-icon name="chevron-left" class="size-4" />
                </button>
            @endif

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 1), min($paginator->lastPage(), $paginator->currentPage() + 1)) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span
                        aria-current="page"
                        class="flex h-7 items-center justify-center rounded-md px-2 text-xs font-medium text-zinc-800 dark:text-white"
                    >
                        {{ $page }}
                    </span>
                @else
                    <button
                        type="button"
                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                        class="flex h-7 items-center justify-center rounded-md px-2 text-xs font-medium text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    >
                        {{ $page }}
                    </button>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    class="flex size-7 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    aria-label="{{ __('pagination.next') }}"
                >
                    <x-icon name="chevron-right" class="size-4" />
                </button>
            @else
                <span class="flex size-7 items-center justify-center text-zinc-300 dark:text-zinc-500">
                    <x-icon name="chevron-right" class="size-4" />
                </span>
            @endif
        </div>
    @endif
</div>
