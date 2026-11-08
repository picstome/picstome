{{--
    Floating action dock for the single-photo viewers.

    One row of icon-only buttons, centred at the bottom and floating over the
    photo. Icon-only keeps the actions readable at a glance: nine labelled
    buttons did not fit and could not be scanned while culling. The dock is
    shared by the owner and the shared viewer so both audiences get the same
    controls in the same place (DRY).

    Buttons keep their `data-*` hooks so the browser tests and shortcuts keep
    working. Groups are separated by `<x-dock-divider>`, and destructive
    actions always come last, tinted red.
--}}
<div
    {{ $attributes->class(['pointer-events-none absolute inset-x-0 bottom-0 z-30 flex justify-center px-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]']) }}
    data-photo-dock
>
    <div
        class="pointer-events-auto flex max-w-full flex-wrap items-center justify-center gap-0.5 rounded-3xl border border-zinc-200/80 bg-white/90 p-1 shadow-xl backdrop-blur-md sm:flex-nowrap sm:rounded-full dark:border-white/10 dark:bg-zinc-900/90"
    >
        {{ $slot }}
    </div>
</div>
