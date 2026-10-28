@props([
    'paginator',
])

@php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $window = 5;
    $start = max(1, min($currentPage - 2, $lastPage - $window + 1));
    $end = min($lastPage, $start + $window - 1);
    $pageName = $paginator->getPageName();
    $pages = $paginator->getUrlRange($start, $end);
@endphp

<div {{ $attributes->class(['flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700']) }}>
    <div class="text-xs font-medium whitespace-nowrap text-zinc-500 dark:text-zinc-400">
        @if ($paginator->total() > 0)
            {{ __('Showing') }} {{ $paginator->firstItem() }} {{ __('to') }} {{ $paginator->lastItem() }}
            {{ __('of') }} {{ $paginator->total() }} {{ __('results') }}
        @endif
    </div>

    @if ($paginator->hasPages())
        <div class="flex items-center gap-0.5 rounded-lg border border-zinc-200 bg-white p-px sm:gap-1 dark:border-white/10 dark:bg-white/10">
            @if ($paginator->onFirstPage())
                <span class="flex size-6 items-center justify-center text-zinc-300 sm:size-7 dark:text-zinc-600" aria-hidden="true">
                    <x-icon name="chevron-double-left" class="size-3.5 sm:size-4" />
                </span>
            @else
                <button
                    type="button"
                    wire:click="gotoPage(1, '{{ $pageName }}')"
                    data-pagination-first
                    class="flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 sm:size-7 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    aria-label="{{ __('First page') }}"
                    title="{{ __('First page') }}"
                >
                    <x-icon name="chevron-double-left" class="size-3.5 sm:size-4" />
                </button>
            @endif

            @if ($paginator->onFirstPage())
                <span class="flex size-6 items-center justify-center text-zinc-300 sm:size-7 dark:text-zinc-600" aria-hidden="true">
                    <x-icon name="chevron-left" class="size-3.5 sm:size-4" />
                </span>
            @else
                <button
                    type="button"
                    wire:click="previousPage('{{ $pageName }}')"
                    class="flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 sm:size-7 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    aria-label="{{ __('Previous page') }}"
                    title="{{ __('Previous page') }}"
                >
                    <x-icon name="chevron-left" class="size-3.5 sm:size-4" />
                </button>
            @endif

            @foreach ($pages as $page => $url)
                @if ($page == $currentPage)
                    <span
                        aria-current="page"
                        class="flex h-6 min-w-6 items-center justify-center rounded-md bg-brand-green px-1.5 text-xs font-semibold text-brand-cream shadow-xs sm:h-7 sm:min-w-7 sm:px-2 dark:bg-brand-sage dark:text-brand-ink-strong"
                    >
                        {{ $page }}
                    </span>
                @else
                    <button
                        type="button"
                        wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                        data-pagination-page="{{ $page }}"
                        class="flex h-6 min-w-6 items-center justify-center rounded-md px-1.5 text-xs font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 sm:h-7 sm:min-w-7 sm:px-2 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    >
                        {{ $page }}
                    </button>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="nextPage('{{ $pageName }}')"
                    class="flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 sm:size-7 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    aria-label="{{ __('Next page') }}"
                    title="{{ __('Next page') }}"
                >
                    <x-icon name="chevron-right" class="size-3.5 sm:size-4" />
                </button>
            @else
                <span class="flex size-6 items-center justify-center text-zinc-300 sm:size-7 dark:text-zinc-600" aria-hidden="true">
                    <x-icon name="chevron-right" class="size-3.5 sm:size-4" />
                </span>
            @endif

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="gotoPage({{ $lastPage }}, '{{ $pageName }}')"
                    data-pagination-last
                    class="flex size-6 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-800 sm:size-7 dark:text-zinc-400 dark:hover:bg-white/20 dark:hover:text-white"
                    aria-label="{{ __('Last page') }}"
                    title="{{ __('Last page') }}"
                >
                    <x-icon name="chevron-double-right" class="size-3.5 sm:size-4" />
                </button>
            @else
                <span class="flex size-6 items-center justify-center text-zinc-300 sm:size-7 dark:text-zinc-600" aria-hidden="true">
                    <x-icon name="chevron-double-right" class="size-3.5 sm:size-4" />
                </span>
            @endif
        </div>
    @endif
</div>
