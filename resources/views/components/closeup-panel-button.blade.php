{{--
    Dock trigger for the Closeup Panel.

    Only shown on desktop (`max-md:hidden`, same breakpoint the panel uses): the
    panel itself is desktop-only, so on mobile the button would do nothing.

    It only dispatches a window event: pressing it is the single moment any
    JavaScript work starts (detector import, image load, rating). Until then
    the panel is inert.

    The trigger mirrors the panel state: the panel dispatches
    `closeup-opened` / `closeup-closed` from `show()` and `close()`, and the
    button switches to the `active` tone while it is open. Two `<template
    x-if>` blocks keep only one button in the DOM, following the same pattern
    as the annotations toggle: `x-show`/plain class toggling is unreliable
    inside this Livewire-rendered dock, where the button's own `inline-flex`
    can win the cascade.

    The initial state comes from the same localStorage key the panel uses, so
    a remembered open panel also renders a highlighted trigger on page load.
--}}
@props([
    'hint' => 'C',
])

<div
    class="contents"
    x-data="{
        active: (() => {
            try {
                return window.localStorage.getItem('picstome-closeup-open') === '1'
            } catch (exception) {
                return false
            }
        })(),
    }"
    x-on:closeup-opened.window="active = true"
    x-on:closeup-closed.window="active = false"
>
    <template x-if="! active">
        <x-dock-button
            icon="viewfinder-circle"
            :label="__('Closeup panel')"
            :hint="$hint"
            class="max-md:hidden"
            aria-pressed="false"
            data-closeup-open
            x-on:click="$dispatch('closeup-open')"
        />
    </template>

    <template x-if="active">
        <x-dock-button
            icon="viewfinder-circle"
            :label="__('Close Closeup Panel')"
            :hint="$hint"
            tone="active"
            class="max-md:hidden"
            aria-pressed="true"
            data-closeup-open
            x-on:click="$dispatch('closeup-close')"
        />
    </template>
</div>
