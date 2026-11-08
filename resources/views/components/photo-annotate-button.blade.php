{{--
    Toggle for annotation mode.

    Icon-only dock buttons, so the label lives in the tooltip and the
    accessible name.

    Same "filled means there is something here" rule, and the same colour, as
    the comments button: once the photo has annotations, **Annotate is filled
    dark** (`ink`), so a photo worth opening annotation mode on reads at a
    glance. A photo without annotations keeps the quiet tone.

    While the mode runs, the button is replaced by **Exit annotation mode**
    (sage tone), which is where the photographer leaves it. Both sit in the
    same `contents` wrapper, so only one occupies the dock slot.

    Visibility is `x-bind:class` with the **important** `hidden!` utility:
    `x-show` is not reliably re-applied inside this Livewire-rendered scope,
    and a plain `hidden` class loses the cascade against the button's own
    `inline-flex`. `hidden!` wins both fights.
--}}
@props([
    'canAnnotate' => false,
    'hasAnnotations' => false,
])

@if ($canAnnotate)
    <div
        class="contents"
        x-data="{ active: false }"
        x-on:annotations-toggled.window="active = $event.detail.active"
    >
        <x-dock-button
            icon="map-pin"
            :label="__('Annotate')"
            :tone="$hasAnnotations ? 'ink' : 'default'"
            x-bind:class="active ? 'hidden!' : ''"
            data-annotate-button
            x-on:click="$dispatch('toggle-annotations')"
        />

        <x-dock-button
            icon="arrow-uturn-left"
            :label="__('Exit annotation mode')"
            tone="active"
            x-bind:class="active ? '' : 'hidden!'"
            data-annotate-exit
            x-on:click="$dispatch('toggle-annotations')"
        />
    </div>
@endif
