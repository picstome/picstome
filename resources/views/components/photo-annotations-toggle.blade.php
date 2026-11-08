{{--
    Hide / show the annotation pins on the photo.

    Only rendered when the photo actually has annotations, and only on the
    shared viewer when the gallery has annotations enabled: a toggle for
    something that cannot exist is noise in the dock.

    A single dock slot whose state is read from its colour, exactly like the
    comments button fills when the photo has comments:
      - pins visible -> **green** (`bg-brand-green`) + `eye`,
        "Hide annotations",
      - pins hidden  -> **sage** (`active` tone) + `eye-slash`,
        "Show annotations".
    Two distinct greens on purpose: the state reads at a glance.

    Each button lives behind its own `<template x-if>`, so only one element
    exists in the DOM at any moment. That is deliberate: `x-show` and class
    toggling are not reliably re-applied inside this Livewire-rendered scope
    (a utility like `hidden` loses the cascade against the button's own
    `inline-flex`), which could leave both buttons painted at once.

    The choice is remembered in localStorage (`picstome-annotations-visible`),
    the same key the annotation overlay reads on mount, so hiding the pins
    keeps them hidden while moving photo to photo in both viewers.
--}}
@props([
    'initialVisible' => true,
])

<div
    x-data="{
        visible: (() => {
            try {
                const stored = window.localStorage.getItem('picstome-annotations-visible')

                return stored === null ? @js($initialVisible) : stored !== '0'
            } catch (exception) {
                return @js($initialVisible)
            }
        })(),
    }"
    x-on:annotations-visibility-changed.window="visible = $event.detail.visible"
    class="contents"
    data-annotations-toggle
>
    <template x-if="visible">
        <x-dock-button
            icon="eye"
            :label="__('Hide annotations')"
            tone="green"
            data-annotations-hide
            x-on:click="$dispatch('toggle-annotations-visibility')"
        />
    </template>

    <template x-if="! visible">
        <x-dock-button
            icon="eye-slash"
            :label="__('Show annotations')"
            tone="active"
            data-annotations-show
            x-on:click="$dispatch('toggle-annotations-visibility')"
        />
    </template>
</div>
