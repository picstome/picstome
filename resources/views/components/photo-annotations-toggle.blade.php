@props([
    'initialVisible' => true,
])

<div
    x-data="{ visible: @js($initialVisible) }"
    x-on:annotations-visibility-changed.window="visible = $event.detail.visible"
    class="contents"
>
    <flux:button
        size="sm"
        icon="eye-slash"
        variant="primary"
        x-show="visible"
        x-on:click="$dispatch('toggle-annotations-visibility')"
    >
        {{ __('Hide annotations') }}
    </flux:button>

    <flux:button
        size="sm"
        icon="eye"
        variant="primary"
        x-show="! visible"
        x-cloak
        style="display: none"
        x-on:click="$dispatch('toggle-annotations-visibility')"
    >
        {{ __('Show annotations') }}
    </flux:button>
</div>
