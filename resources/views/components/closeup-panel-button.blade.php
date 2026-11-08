{{--
    Dock trigger for the Closeup Panel.

    Only shown on desktop (`max-md:hidden`, same breakpoint the panel uses): the
    panel itself is desktop-only, so on mobile the button would do nothing.

    It only dispatches a window event: pressing it is the single moment any
    JavaScript work starts (detector import, image load, rating). Until then
    the panel is inert.
--}}
@props([
    'hint' => 'C',
])

<x-dock-button
    icon="viewfinder-circle"
    :label="__('Closeup panel')"
    :hint="$hint"
    class="max-md:hidden"
    data-closeup-open
    x-on:click="$dispatch('closeup-open')"
/>
