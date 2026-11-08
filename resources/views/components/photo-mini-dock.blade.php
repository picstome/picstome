{{--
    Mini dock for a gallery grid thumbnail.

    Deliberately the same visual language as the big viewer dock
    (`<x-photo-dock>` + `<x-dock-button>`): one frosted pill holding icon-only
    buttons, so the grid and the single-photo view feel like the same product
    instead of two different toolbars.

    Smaller on purpose (`compact` buttons, tighter padding): the tile is a
    fraction of the screen and the actions must not cover the thumbnail.

    It is centred horizontally at the bottom of the thumbnail (`inset-x-0` +
    `justify-center`), matching where the viewer dock sits, rather than tucked
    into a corner: the same actions should be in the same place at both zoom
    levels.
--}}
<div {{ $attributes->class(['absolute inset-x-0 bottom-1.5 flex justify-center']) }}>
    <div class="pointer-events-auto flex items-center gap-0.5 rounded-full border border-zinc-200/80 bg-white/90 p-0.5 shadow-lg backdrop-blur-md dark:border-white/10 dark:bg-zinc-900/90">
        {{ $slot }}
    </div>
</div>
