{{--
    Icon-only button for the photo dock.

    Rendered as a plain <a>/<button> instead of a TallStackUI button on purpose:
    TallStackUI composes its own padding and height, so a filled tone (ink,
    danger) painted a shorter block inside the dock pill and left visible gaps.
    Here the button is one fixed square (36 x 36) with a full-height colour
    fill, so every tone looks identical in size and only the colour changes.

    The dock has no labels (nine labelled buttons did not fit), so the tooltip
    is how the action is discovered. `hint` adds the keyboard shortcut, e.g.
    "Favorite (F)".

    Tones: default quiet, ink filled dark, danger filled coral (destructive),
    favorite filled red (a chosen photo reads as a red heart), accent filled
    with the public branding colour, active tinted.
    Favourite is a true red, not the coral of the destructive tone: the two
    must never be mistaken for each other at a glance.

    `compact` renders a 28 px square for the mini dock that sits on each grid
    thumbnail, so the same button component keeps the grid and the viewer
    visually consistent.

    `accent` reads `--color-accent`, which the guest layout sets from the
    photographer's brand colour, so a public gallery highlights its primary
    action in the colour the studio actually chose.

    A caller may pass its own icon through the slot instead of `icon` (the
    annotations toggle swaps between `eye` and `eye-slash` in place, keeping
    one dock slot so the buttons around it do not shift).
--}}
@props([
    'icon',
    'label',
    'hint' => null,
    'tone' => 'default',
    'href' => null,
    'compact' => false,
])

@php
    $tooltip = $hint ? $label.' ('.$hint.')' : $label;

    $toneClasses = match ($tone) {
        'ink' => 'bg-brand-ink text-brand-cream hover:bg-brand-ink-strong dark:bg-brand-sage dark:text-brand-ink-strong dark:hover:bg-brand-sage/90',
        'danger' => 'bg-brand-coral text-brand-cream hover:bg-brand-coral-deep',
        'favorite' => 'bg-red-600 text-white hover:bg-red-700',
        'accent' => 'bg-(--color-accent) text-(--color-accent-foreground) hover:bg-(--color-accent-content)',
        'green' => 'bg-brand-green text-brand-cream hover:bg-brand-green-deep',
        'active' => 'bg-brand-sage text-brand-ink-strong hover:bg-brand-sage/80',
        default => 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-300 dark:hover:bg-white/10 dark:hover:text-white',
    };

    $tag = $href ? 'a' : 'button';

    $sizeClasses = $compact
        ? 'size-7'
        : 'size-8 sm:size-9';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="button" @endif
    x-tooltip="{{ $tooltip }}"
    aria-label="{{ $tooltip }}"
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full transition',
        $sizeClasses,
        $toneClasses,
    ]) }}
>
    @if ($slot->isEmpty())
        <x-icon :name="$icon" class="{{ $compact ? 'size-3.5' : 'size-4' }}" />
    @else
        {{ $slot }}
    @endif
</{{ $tag }}>
