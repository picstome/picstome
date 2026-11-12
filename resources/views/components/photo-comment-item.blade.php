@props([
    'comment',
    'canDelete' => false,
    'alignRight' => false,
    'currentVersionNumber' => 1,
    'versionNumbers' => [],
])

@php
    $isAnnotation = $comment->hasCoordinates();
    $isTimestamp = $comment->hasTimestamp();
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
            <x-ts-text variant="strong" class="text-sm font-semibold">
                {{ $comment->user->name }}
            </x-ts-text>
            <x-ts-text>&middot;</x-ts-text>
        @endif

        <x-ts-text class="text-xs">
            {{ $comment->created_at->diffForHumans() }}
        </x-ts-text>

        @if ($isAnnotation)
            <x-icon name="map-pin"
                class="text-brand-green dark:text-brand-sage size-3.5 shrink-0"
                :title="__('Photo annotation')"
            />
        @endif

        @if ($isTimestamp)
            {{--
                The chip is the way back into the video: clicking it seeks the
                player to the exact second and closes the composer.
            --}}
            <button
                type="button"
                class="bg-brand-green text-brand-cream hover:bg-brand-green-deep inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-[11px] font-semibold tabular-nums transition"
                title="{{ __('Jump to :time', ['time' => $comment->timecode()]) }}"
                x-on:click="
                    $dispatch('video-seek', { seconds: {{ (float) $comment->time_seconds }} })
                    $dispatch('modal:add-comment-close')
                "
                data-comment-time="{{ $comment->timecode() }}"
            >
                <x-icon name="play" variant="solid" class="size-2.5" />
                {{ $comment->timecode() }}
            </button>
        @endif

        @if ($isOtherVersion)
            <x-ts-badge size="sm" color="amber" variant="pill">
                {{ __('Version :number', ['number' => $comment->annotated_version_number]) }}
            </x-ts-badge>
        @endif

        @if ($canDelete)
            <x-ts-button
                wire:click="requestDeleteComment({{ $comment->id }})"
                icon="x-mark"
                variant="subtle"
                size="xs"
                inset="right"
                square
            />
        @endif
    </div>

    <x-ts-text variant="strong">
        {{ $comment->comment }}
    </x-ts-text>

    @if ($isOtherVersion)
        <x-ts-text class="mt-1 block text-xs text-amber-600 dark:text-amber-400">
            {{ __('This annotation was made on version :number.', ['number' => $comment->annotated_version_number]) }}
            @unless ($versionAvailable)
                {{ __('That version is no longer available.') }}
            @endunless
        </x-ts-text>
    @endif
</div>
