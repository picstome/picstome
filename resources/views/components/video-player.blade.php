{{--
    Video player with a custom control bar.
    
    Used by the owner viewer, the shared viewer and the video version
    comparison. It replaces the native `controls` attribute so the app can:
    - draw timestamped comment markers on the rail,
    - hand the current second to the comment composer,
    - remember the last played second in this browser,
    - and drive two videos from one bar in the comparison modal.
    
    The `videoPlayer` Alpine factory comes from
    partials/video-player-script, which is only included when a video is on
    screen; images and PDFs never load it.
    
    Keyboard shortcuts are scoped to this element (`tabindex="0"`): clicking
    the video focuses the player, so the photo viewer's arrow-key navigation
    keeps working until the visitor actually starts watching.
--}}

@props([
    'photo',
    'comments' => [],
    'markers' => null,
    'startAt' => null,
    'persist' => true,
    'compact' => false,
])

@php
    // Timestamped comments become markers on the rail. The server renders the
    // label, so the marker tooltip and the comment chip always agree. The
    // viewer can pass a prebuilt list (`markers`) so a comment write can push
    // the same shape at runtime without re-rendering this component.
    if ($markers === null) {
        $markers = collect($comments)
            ->filter(fn ($comment) => $comment->hasTimestamp())
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'time' => (float) $comment->time_seconds,
                'label' => $comment->timecode(),
                'aria' => __('Jump to :time', ['time' => $comment->timecode()]),
            ])
            ->values()
            ->all();
    }

    $persistKey = $persist ? 'picstome-video-position:'.$photo->id : null;
    $sourceType = 'video/'.pathinfo($photo->path, PATHINFO_EXTENSION);
    $videoUrl = $photo->video_url ?? $photo->url;
@endphp

@php
    // No autoplay, by design. A gallery is browsed in bursts, and a video that
    // starts by itself steals attention and makes the volume control useless
    // (browsers only autoplay muted). The visitor presses play when they want
    // to watch.
@endphp

{{--
    The server re-renders the marker list after a comment is added or deleted;
    Alpine does not re-evaluate `x-data` on a Livewire morph, so the new list
    is pushed through `video-markers-updated`.
--}}
<div
    x-data="videoPlayer({
        persistKey: @js($persistKey),
        startAt: @js($startAt !== null ? (float) $startAt : null),
        markers: @js($markers),
    })"
    x-on:keydown="handleKeydown($event)"
    x-on:keyup="handleKeyup($event)"
    x-on:video-seek.window="seek($event.detail.seconds ?? $event.detail.time)"
    x-on:video-capture-time.window="captureForComment()"
    x-on:video-markers-updated.window="
        if ($event.detail.photoId !== {{ $photo->id }}) {
            return
        }
        setMarkers($event.detail.markers)
    "
    tabindex="0"
    data-video-player
    {{-- The player hugs the video instead of filling the viewport: a 16:9 clip
         at page width is taller than the space between the header and the
         dock, which pushes the control bar to the very bottom of the screen,
         far from the picture it controls. Capping the height keeps the video
         centred with the bar attached to it, and `m-auto` centres it inside
         the flex container. --}}
    {{ $attributes->class(['group relative m-auto inline-flex max-h-[calc(100dvh-9rem)] w-auto max-w-[min(100%,1280px)] items-center justify-center focus:outline-none']) }}
>
    <video
        x-ref="video"
        x-on:click="
            toggle()
            $el.closest('[data-video-player]')?.focus()
        "
        class="block max-h-[calc(100dvh-9rem)] w-auto max-w-[min(100%,1280px)] bg-black object-contain"
        playsinline
        preload="metadata"
        {{-- Unmuted on purpose: nothing starts until play is pressed, so the
             volume slider means something from the first frame. --}}
        {{-- Native controls are the safety net. They ship in the HTML, so a
             page where the Alpine factory never registers (stale compiled
             view, JS error, blocked script) still plays the video. Once
             Alpine boots, `x-bind` takes over and hides them as soon as the
             custom control bar can render. --}}
        controls
        x-bind:controls="! canPlay"
        data-video-element
    >
        <source src="{{ $videoUrl }}" type="{{ $sourceType }}" />
        {{ __('Your browser cannot play this video.') }}
    </video>

    {{--
        Loading spinner: visible during the initial load of a large file (when
        `readyState` is still 0) and whenever the browser is waiting for more
        data. Without it a slow 19 MB video looks like an empty black box.
    --}}
    <div
        x-cloak
        x-show="(! paused || ! ready) && ! errored"
        class="pointer-events-none absolute inset-0 flex items-center justify-center"
    >
        <span class="size-10 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
    </div>

    <div
        x-cloak
        x-show="errored"
        class="absolute inset-x-4 top-4 rounded-lg bg-red-600/90 p-3 text-center text-sm text-white"
    >
        {{ __('This video could not be loaded.') }}
    </div>

    {{--
        Control bar, pinned to the bottom edge of the video itself.

        It is not lifted above the floating dock any more: the player now hugs
        the video instead of filling the viewport, so the two rarely meet, and
        a hardcoded offset only pushed the bar into the middle of the picture.
    --}}
    <div
        x-show="canPlay"
        x-cloak
        class="absolute inset-x-0 bottom-0 z-20 bg-zinc-950/85 px-3 pt-3 pb-3 backdrop-blur-sm"
        data-video-controls
    >
        <div class="relative z-10 mb-2 flex h-4 items-center">
            {{--
                Rail: played portion, markers and a transparent native range
                that provides keyboard and screen-reader interaction.
            --}}
            <div class="absolute inset-x-0 h-1.5 overflow-hidden rounded-full bg-white/25">
                <div class="bg-brand-sage h-full rounded-full" :style="`width: ${progress}%`"></div>
            </div>

            <template x-for="marker in railMarkers" :key="marker.id">
                <button
                    type="button"
                    class="absolute top-1/2 z-20 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-amber-300 ring-1 ring-black/40 transition hover:scale-150"
                    :style="`left: ${marker.left}%`"
                    x-bind:title="marker.label"
                    x-bind:aria-label="marker.aria"
                    x-on:click.stop="seek(marker.time)"
                    data-video-marker
                ></button>
            </template>

            <input
                type="range"
                min="0"
                step="0.05"
                x-bind:max="duration || 1"
                x-bind:value="dragging ? dragValue : currentTime"
                x-on:pointerdown="startDragging()"
                x-on:input="updateDrag($event)"
                x-on:change="commitDrag($event)"
                class="absolute inset-x-0 z-10 h-4 w-full cursor-pointer appearance-none bg-transparent opacity-0"
                x-bind:aria-label="@js(__('Seek video'))"
                data-video-seek
            />
        </div>

        <div class="relative z-10 flex items-center gap-1 text-white">
            <button
                type="button"
                x-on:click="toggle()"
                class="grid size-9 place-items-center rounded-full transition hover:bg-white/15"
                x-bind:aria-label="paused ? @js(__('Play')) : @js(__('Pause'))"
                data-video-toggle
            >
                <x-icon x-show="paused" name="play" variant="solid" class="size-5 translate-x-px" />
                <x-icon x-show="! paused" name="pause" variant="solid" class="size-5" x-cloak />
            </button>

            <span class="ml-1 shrink-0 font-mono text-xs tabular-nums" data-video-time>
                <span x-text="displayCurrentTime"></span>
                <span class="text-white/50">/</span>
                <span x-text="displayDuration"></span>
            </span>

            <div class="flex-1"></div>

            @unless ($compact)
                <div class="hidden items-center gap-1 sm:flex">
                    <button
                        type="button"
                        x-on:click="toggleMute()"
                        class="grid size-8 place-items-center rounded-full transition hover:bg-white/15"
                        x-bind:aria-label="muted ? @js(__('Unmute')) : @js(__('Mute'))"
                        data-video-mute
                    >
                        <x-icon x-show="! muted" name="speaker-wave" class="size-4" />
                        <x-icon x-show="muted" name="speaker-x-mark" class="size-4" x-cloak />
                    </button>

                    <input
                        type="range"
                        min="0"
                        max="1"
                        step="0.05"
                        x-on:input="setVolume($event)"
                        x-bind:value="muted ? 0 : volume"
                        class="h-1 w-16 cursor-pointer accent-white"
                        x-bind:aria-label="@js(__('Volume'))"
                        data-video-volume
                    />
                </div>
            @endunless

            <button
                type="button"
                x-on:click="cycleRate()"
                class="min-w-11 rounded-full px-2 py-1 text-xs font-semibold tabular-nums transition hover:bg-white/15"
                x-bind:aria-label="`@js(__('Playback speed')): ${rate}x`"
                data-video-rate
                x-text="rate + 'x'"
            ></button>

            @unless ($compact)
                <button
                    type="button"
                    x-on:click="toggleFullscreen()"
                    class="grid size-8 place-items-center rounded-full transition hover:bg-white/15"
                    aria-label="{{ __('Fullscreen') }}"
                    data-video-fullscreen
                >
                    <x-icon name="arrows-pointing-out" class="size-4" />
                </button>
            @endunless
        </div>
    </div>
</div>
