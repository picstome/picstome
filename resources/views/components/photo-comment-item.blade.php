@props([
    'comment',
    'canDelete' => false,
    'alignRight' => false,
    'currentVersionNumber' => 1,
    'versionNumbers' => [],
])

@php
    $isAnnotation = $comment->hasCoordinates();
    $isOtherVersion = $comment->isFromOtherVersion($currentVersionNumber);
    $versionAvailable = in_array((int) $comment->annotated_version_number, $versionNumbers, true);
@endphp

<div
    id="photo-comment-{{ $comment->id }}"
    x-data="{ highlighted: false }"
    x-on:focus-comment.window="
        if ($event.detail.id !== {{ $comment->id }}) return;
        highlighted = true;
        setTimeout(() => $el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }), 200);
        setTimeout(() => highlighted = false, 2500);
    "
    x-bind:class="highlighted ? 'ring-2 ring-brand-green dark:ring-brand-sage' : ''"
    @class([
        'group relative rounded bg-zinc-50 p-3 transition dark:bg-zinc-900',
        'ml-auto text-right' => $alignRight,
    ])
>
    <div
        @class([
            'mb-1 flex items-center gap-2',
            'justify-end text-right' => $alignRight,
        ])
    >
        @if ($comment->user)
            <flux:text variant="strong" class="text-sm font-semibold">
                {{ $comment->user->name }}
            </flux:text>
            <flux:text>&middot;</flux:text>
        @endif

        <flux:text class="text-xs">
            {{ $comment->created_at->diffForHumans() }}
        </flux:text>

        @if ($isAnnotation)
            <flux:icon.map-pin
                class="text-brand-green dark:text-brand-sage size-3.5 shrink-0"
                :title="__('Photo annotation')"
            />
        @endif

        @if ($isOtherVersion)
            <flux:badge size="sm" color="amber" variant="pill">
                {{ __('Version :number', ['number' => $comment->annotated_version_number]) }}
            </flux:badge>
        @endif

        @if ($canDelete)
            <flux:button
                wire:click="requestDeleteComment({{ $comment->id }})"
                icon="x-mark"
                variant="subtle"
                size="xs"
                inset="right"
                square
            />
        @endif
    </div>

    <flux:text variant="strong">
        {{ $comment->comment }}
    </flux:text>

    @if ($isOtherVersion)
        <flux:text class="mt-1 block text-xs text-amber-600 dark:text-amber-400">
            {{ __('This annotation was made on version :number.', ['number' => $comment->annotated_version_number]) }}
            @unless ($versionAvailable)
                {{ __('That version is no longer available.') }}
            @endunless
        </flux:text>
    @endif
</div>
